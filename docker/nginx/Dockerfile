# Stage 1: Build Frontend
FROM node:20-alpine AS frontend-builder
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

# Copy Laravel public assets
COPY backend/public /var/www/html/public

# Copy built frontend assets to the /app subdirectory
COPY --from=frontend-builder /app/dist /var/www/html/public/app

# Create storage directories to ensure Nginx can alias them (they will be shadowed by volumes)
RUN mkdir -p /var/www/html/storage/app/public /var/www/html/public/upload

EXPOSE 80
