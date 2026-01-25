# СТАДИЯ 1: Сборка (тяжелая, с Node.js)
FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile   # Кэшируется, пока не измените package.json
COPY . .
RUN yarn build                      # Генерирует файлы в public/build

# СТАДИЯ 2: Финальный образ (легкий, только Nginx)
FROM nginx:1.27-alpine
COPY ./docker/common/nginx/conf.d /etc/nginx/conf.d
WORKDIR /app
COPY ./public ./public
# Забираем только результат из первой стадии
COPY --from=node-builder /app/public/build ./public/build
