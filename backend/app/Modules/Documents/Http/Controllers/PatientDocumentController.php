<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Documents\Application\ConsumePatientDocumentDownload;
use App\Modules\Documents\Application\CreatePatientDocumentDownloadAuthorization;
use App\Modules\Documents\Application\ListPatientDocuments;
use App\Modules\Documents\Http\Resources\PatientDocumentResource;
use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Application\AuditPortalAction;
use App\Modules\Patients\Application\ResolvePortalPatient;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Expose audited, ownership-scoped patient document listing and downloads.
 */
final class PatientDocumentController extends Controller
{
    /**
     * Return the authenticated patient's currently published documents.
     */
    public function index(
        Request $request,
        ResolvePortalPatient $resolvePatient,
        ListPatientDocuments $documents,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $items = $documents->handle($patient);
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.documents.listed',
            'patient',
            $patient->public_id,
            ['item_count' => $items->count()],
        );

        return response()->json([
            'data' => $items
                ->map(static fn (ClinicalDocument $document): array => (new PatientDocumentResource($document))->toArray($request))
                ->values()
                ->all(),
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    /**
     * Issue a short-lived authorization URL for one owned document.
     */
    public function authorizeDownload(
        Request $request,
        string $document,
        ResolvePortalPatient $resolvePatient,
        CreatePatientDocumentDownloadAuthorization $authorize,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);

        try {
            $authorization = $authorize->handle($identity, $patient, strtolower($document));
        } catch (ModelNotFoundException $exception) {
            $audit->denied(
                $request,
                $identity,
                $patient,
                'patient.document.download_authorization_denied',
                'document',
                strtolower($document),
                ['reason_code' => 'not_found_or_not_published'],
            );

            throw $exception;
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.document.download_authorization_failed',
                'document',
                strtolower($document),
                ['reason_code' => 'integrity_or_storage_failure'],
            );

            throw $exception;
        }

        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.document.download_authorized',
            'document',
            strtolower($document),
            [
                'grant_public_id' => $authorization->grant->public_id,
                'ttl_seconds' => (int) config('documents.download_grant_ttl_seconds'),
            ],
        );

        return response()->json([
            'data' => [
                'document_id' => strtolower($document),
                'download_url' => '/api/v1/patient/document-downloads/'.$authorization->token,
                'expires_at' => $authorization->grant->expires_at->utc()->format(DATE_ATOM),
            ],
            'meta' => ['request_id' => $this->requestId($request)],
        ], 201);
    }

    /**
     * Consume a one-time grant and stream the verified PDF attachment.
     */
    public function download(
        Request $request,
        string $token,
        ResolvePortalPatient $resolvePatient,
        ConsumePatientDocumentDownload $consume,
        AuditPortalAction $audit,
    ): Response {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);

        try {
            $download = $consume->handle(
                $identity,
                $patient,
                $token,
                $this->requestId($request),
            );
        } catch (ModelNotFoundException $exception) {
            $audit->denied(
                $request,
                $identity,
                $patient,
                'patient.document.download_denied',
                'patient',
                $patient->public_id,
                ['reason_code' => 'invalid_expired_consumed_or_unpublished_grant'],
            );

            throw $exception;
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.document.download_failed',
                'patient',
                $patient->public_id,
                ['reason_code' => $exception instanceof RuntimeException ? 'integrity_failure' : 'delivery_failure'],
            );

            throw $exception;
        }

        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.document.downloaded',
            'document',
            $download->document->public_id,
            [
                'version_public_id' => $download->version->public_id,
                'grant_public_id' => $download->grant->public_id,
            ],
        );

        return response($download->contents, 200, [
            'Content-Type' => $download->version->mime_type,
            'Content-Length' => (string) strlen($download->contents),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $download->fileName(),
            ),
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Resolve the authenticated web identity or fail through Laravel's auth flow.
     */
    private function identity(Request $request): IdentityAccount
    {
        $identity = $request->user('web');
        if (! $identity instanceof IdentityAccount) {
            throw new AuthenticationException(guards: ['web']);
        }

        return $identity;
    }

    /**
     * Return the middleware-issued correlation ID required by document audits.
     */
    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get(AssignRequestId::ATTRIBUTE);
        if (! is_string($requestId)) {
            throw new RuntimeException('A document operation requires a request identifier.');
        }

        return $requestId;
    }
}
