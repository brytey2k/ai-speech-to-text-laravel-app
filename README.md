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

## Requirements

- Docker and Docker Compose
- OpenAI API key for Whisper API access

## Setup Instructions

### 1. Clone the Repository

```bash
git clone <repository-url>
cd speech-to-text
```

### 2. Environment Configuration

Copy the example environment file and update it with your settings:

```bash
cp .env.example .env
```

Make sure to set your OpenAI API key in the `.env` file:

```
OPENAI_API_KEY=your-api-key-here
```

### 3. Start the Application with Laravel Sail

```bash
./vendor/bin/sail up -d
```

This command will start all the necessary Docker containers:
- Laravel application (PHP 8.4)
- MySQL database
- Redis for caching and queues
- Queue worker for processing transcriptions
- Reverb for WebSockets

### 4. Install Dependencies and Run Migrations

```bash
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

### 5. Access the Application

Open your browser and navigate to:

```
http://localhost
```

## Usage

1. Click the "Start Transcription" button to begin recording
2. Speak into your microphone
3. The application will automatically detect speech segments and process them
4. View transcriptions in the sidebar as they are processed
5. Click on audio controls to replay recorded segments

## Technical Details

- Built with Laravel PHP framework
- Uses Laravel Octane with FrankenPHP for improved performance
- Processes audio transcriptions asynchronously using Laravel queues
- Leverages OpenAI's Whisper API for accurate speech-to-text conversion
- Real-time updates via Laravel Reverb WebSockets

## License

[MIT License](LICENSE)
