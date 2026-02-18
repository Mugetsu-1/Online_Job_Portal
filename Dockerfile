FROM php:8.2-cli
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_pgsql
WORKDIR /app
COPY . .
RUN mkdir -p /app/backend/uploads/resumes /app/backend/uploads/profiles /app/backend/uploads/logos
ENV PORT=8080
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /app"]
