# syntax=docker/dockerfile:1.7

ARG NODE_IMAGE=node:24.18.0-alpine3.24@sha256:a0b9bf06e4e6193cf7a0f58816cc935ff8c2a908f81e6f1a95432d679c54fbfd
ARG NGINX_IMAGE=nginxinc/nginx-unprivileged:1.30.4-alpine3.24@sha256:dcea25a6593307a74b09e59a47f8695c4d56943750e45add532ae0bf8b24bfd6

FROM ${NODE_IMAGE} AS build

ARG APP_NAME

WORKDIR /workspace

COPY frontend/package.json frontend/package-lock.json ./

# Native install scripts are blocked. Angular/esbuild resolve through locked
# platform packages, and the complete lifecycle is exercised again in CI.
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY frontend/angular.json frontend/eslint.config.js frontend/tsconfig.json ./
COPY frontend/projects/ ./projects/

RUN test -n "${APP_NAME}"; \
    npm run build:libs; \
    ./node_modules/.bin/ng build "${APP_NAME}" --configuration production; \
    test -f "dist/${APP_NAME}/browser/index.html"

FROM ${NGINX_IMAGE}

ARG APP_NAME

COPY --chown=101:101 infrastructure/docker/frontend/frontend.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build --chown=101:101 /workspace/dist/${APP_NAME}/browser/ /usr/share/nginx/html/

ENV API_UPSTREAM=http://api-proxy:8080 \
    NGINX_ENVSUBST_FILTER=^API_UPSTREAM$

USER 101:101

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD ["wget", "--quiet", "--spider", "http://127.0.0.1:8080/healthz"]
