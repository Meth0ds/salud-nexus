# syntax=docker/dockerfile:1.7

ARG NGINX_IMAGE=nginxinc/nginx-unprivileged:1.30.4-alpine3.24@sha256:dcea25a6593307a74b09e59a47f8695c4d56943750e45add532ae0bf8b24bfd6

FROM ${NGINX_IMAGE}

COPY --chown=101:101 infrastructure/docker/api-proxy/api.conf.template /etc/nginx/templates/default.conf.template
COPY --chown=101:101 backend/public/ /srv/app/public/

ENV FORWARDED_PROTO=http \
    NGINX_ENVSUBST_FILTER=^FORWARDED_PROTO$

USER 101:101

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD ["wget", "--quiet", "--spider", "http://127.0.0.1:8080/api/v1/health/live"]
