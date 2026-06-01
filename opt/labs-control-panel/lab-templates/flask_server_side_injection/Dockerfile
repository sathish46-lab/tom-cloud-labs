# Lightweight Python image for Flask SSTI Challenge
FROM python:3.11-slim

# Install Flask
RUN pip install --no-cache-dir flask==3.1.1

# Set working directory
WORKDIR /var/www/html

# Copy the vulnerable Flask application
COPY app.py /var/www/html/app.py

# The flag will be injected at runtime by the orchestrator into /var/www/html/flag.txt
# This placeholder ensures the directory and path exist
RUN echo "CTF{placeholder_flag}" > /var/www/html/flag.txt

# Expose port 80
EXPOSE 80

# Run Flask on port 80
CMD ["python", "app.py"]
