# Stage 1: Сборка фронтенда
FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY ./ ./
RUN yarn build

# Stage 2: Nginx
FROM nginx:1.27-alpine

RUN apk add --no-cache curl

COPY ./docker/common/nginx/conf.d /etc/nginx/conf.d

WORKDIR /app

# Копируем статику из основного контекста и собранные ассеты из node-builder
COPY ./public ./public
COPY --from=node-builder /app/public/build ./public/build

HEALTHCHECK --interval=5s --timeout=3s --retries=3 \
    CMD curl -f http://localhost/health || exit 1
