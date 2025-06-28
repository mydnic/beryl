FROM nginx:alpine

# Remove default nginx configuration
RUN rm /etc/nginx/conf.d/default.conf

# Copy custom nginx configuration
COPY docker/nginx/app.conf /etc/nginx/conf.d/

# Create directory for the application
RUN mkdir -p /var/www/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
