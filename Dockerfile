
# Use lightweight Nginx image
FROM nginx:alpine

# Copy all static files to Nginx's default web directory
COPY . /usr/share/nginx/html

# Expose port 80 for HTTP traffic
EXPOSE 80

# Start Nginx in the foreground
CMD ["nginx", "-g", "daemon off;"]
