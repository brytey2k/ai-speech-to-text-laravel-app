# Darli - Speech-to-Text Transcription Application

Darli is a web application that provides real-time speech-to-text transcription using OpenAI's Whisper API. It allows users to record audio through their browser, processes the audio segments, and displays the transcribed text.

## Features

- **Real-time Audio Recording**: Record audio directly from your browser with a simple interface
- **Voice Activity Detection (VAD)**: Automatically detects speech segments
- **Waveform Visualization**: Visual representation of audio input
- **Asynchronous Processing**: Background processing of audio transcriptions using Laravel queues
- **Transcription History**: View and playback all previous transcriptions
- **Status Tracking**: Monitor the status of each transcription (pending, in progress, success, failed)
- **Real-time Updates**: Receive real-time updates on transcription status via WebSockets

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+). Project uses PHP 8.4 from docker compose setup.
- **Frontend**: JavaScript with Tailwind CSS
- **Database**: MySQL
- **Caching & Queues**: Redis
- **WebSockets**: Laravel Reverb
- **Performance**: Laravel Octane with FrankenPHP
- **Containerization**: Docker with Laravel Sail
- **Key Libraries**:
  - [@ricky0123/vad-web](https://github.com/ricky0123/vad) - Voice Activity Detection
  - [wavesurfer.js](https://wavesurfer-js.org/) - Audio waveform visualization
  - [Laravel Echo](https://laravel.com/docs/broadcasting#client-side-installation) - WebSocket client

## Requirements

- Docker and Docker Compose
- OpenAI API key for Whisper API access
- Git
- PHP 8.4+ (only for initial `composer install` run to install laravel sail)

## Setup Instructions

### 1. Clone the Repository

```bash
git clone <repository-url>
cd ai-speech-to-text-laravel-app
```

### 2. Environment Configuration

Copy the example environment file and update it with your settings:

```bash
cp .env.example .env
```

Required environment variables to configure:

```
# Application settings
APP_URL=http://localhost

# Database settings
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=speech_to_text
DB_USERNAME=sail
DB_PASSWORD=password

# Redis settings
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue settings
QUEUE_CONNECTION=redis

# OpenAI API settings
OPENAI_API_KEY=your-api-key-here

BROADCAST_CONNECTION=reverb

# Reverb WebSocket settings
REVERB_APP_ID=123654
REVERB_APP_KEY="aDummyKeyForDevelopmentPurposes"
REVERB_APP_SECRET="aDummySecretForDevelopmentPurposes"
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="localhost"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### 3. Prepare and start application

```bash
# Install Laravel Sail
composer install

# Start all containers
./vendor/bin/sail up -d
```

This command will start all the necessary Docker containers:
- Laravel application (PHP 8.4+)
- MySQL database
- Redis for caching and queues
- Queue worker for processing transcriptions
- Reverb for WebSockets

### 4. Install Dependencies and Run Migrations

```bash
# Install PHP dependencies
./vendor/bin/sail composer install

# Generate application key
./vendor/bin/sail artisan key:generate

# Run database migrations
./vendor/bin/sail artisan migrate

# storage link
./vendor/bin/sail artisan storage:link

# Install JavaScript dependencies
./vendor/bin/sail npm install

# Build frontend assets (development mode)
./vendor/bin/sail npm run dev

# Or for production build
./vendor/bin/sail npm run build
```

### 5. Access the Application

Open your browser and navigate to:

```
http://localhost
```

## Development Workflow

For local development, you can use the following commands:

```bash
# Run tests
./vendor/bin/sail composer test

# Code linting
./vendor/bin/sail composer pint

# Static analysis
./vendor/bin/sail composer phpstan
```

## Usage

1. Click the "Start Transcription" button to begin recording
2. Speak into your microphone
3. The application will automatically detect speech segments and process them
4. View transcriptions in the sidebar as they are processed
5. Click on audio controls to replay recorded segments
