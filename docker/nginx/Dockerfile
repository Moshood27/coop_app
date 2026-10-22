# Stage 1: Build Frontend
FROM node:22-alpine AS frontend-builder
WORKDIR /app
COPY frontend/package*.json ./
RUN npm install
COPY frontend ./
RUN npm run build

# Stage 2: Final Nginx Image
FROM nginx:1.25-alpine

WORKDIR /var/www/html

# Copy Nginx configurations
COPY docker/nginx/conf.d/app.pro.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/conf.d/.htpasswd /etc/nginx/conf.d/.htpasswd

# Ensure Nginx logs to files instead of stdout/stderr so Fail2Ban on host can monitor them
RUN unlink /var/log/nginx/access.log && \
    unlink /var/log/nginx/error.log && \
    touch /var/log/nginx/access.log /var/log/nginx/error.log && \
    chmod 666 /var/log/nginx/access.log /var/log/nginx/error.log

# Copy Laravel public assets
COPY backend/public /var/www/html/public

# Copy PWA icons from root icons directory
COPY icons /var/www/html/public/icons

# Copy built frontend assets to the /app subdirectory
COPY --from=frontend-builder /app/dist /var/www/html/public/app

# Create storage directories to ensure Nginx can alias them (they will be shadowed by volumes)
RUN mkdir -p /var/www/html/storage/app/public /var/www/html/public/upload

EXPOSE 80
