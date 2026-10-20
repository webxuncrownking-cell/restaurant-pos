import express from 'express';
import http from 'http';
import { spawn, ChildProcess } from 'child_process';
import fs from 'fs';

// Safely attempt to start MariaDB if service script exists
if (fs.existsSync('/etc/init.d/mariadb')) {
  try {
    const dbProc = spawn('/etc/init.d/mariadb', ['start'], { stdio: 'ignore' });
    dbProc.on('error', () => {
      // Ignored: service may already be running or running under different user
    });
  } catch (e) {
    // ignore
  }
}

// Start PHP built-in server on 127.0.0.1:8080 serving pure PHP 8.2 backend
let phpProcess: ChildProcess | null = null;

function startPhpServer() {
  try {
    phpProcess = spawn('php', ['-S', '127.0.0.1:8080', 'index.php'], {
      stdio: 'inherit'
    });
    phpProcess.on('error', (err) => {
      console.warn('[PHP Process Warning]:', err.message);
    });
    phpProcess.on('exit', (code) => {
      console.log('[PHP Process Exited with code]:', code);
    });
  } catch (err: any) {
    console.warn('[PHP Spawn Error]:', err?.message);
  }
}

startPhpServer();

process.on('exit', () => {
  if (phpProcess) {
    try {
      phpProcess.kill();
    } catch (e) {}
  }
});

const app = express();
const PORT = 3000;

// Reverse proxy all incoming preview traffic directly to PHP application on port 8080
app.use((req, res) => {
  const options: http.RequestOptions = {
    hostname: '127.0.0.1',
    port: 8080,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      host: req.headers.host || 'localhost:3000',
      'x-forwarded-host': req.headers.host || 'localhost:3000',
      'x-forwarded-proto': 'http',
    }
  };

  const proxyReq = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 200, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });

  proxyReq.on('error', (err) => {
    // If PHP server is still booting up or unavailable, return a responsive loading / diagnostic screen
    res.writeHead(502, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(`
      <!DOCTYPE html>
      <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>Restaurant POS - Initializing</title>
          <meta http-equiv="refresh" content="2">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        </head>
        <body class="bg-dark text-white d-flex align-items-center justify-content-center" style="min-height: 100vh;">
          <div class="card bg-secondary text-white p-4 shadow-lg border-0" style="max-width: 520px;">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="spinner-border text-primary" role="status"></div>
              <h4 class="mb-0 fw-bold">Initializing Restaurant POS...</h4>
            </div>
            <p class="small text-white-50 mb-3">
              The application engine is starting. This page will refresh automatically once the backend is ready.
            </p>
            <div class="bg-dark p-3 rounded font-monospace small text-warning mb-0">
              Gateway status: ${err.message}
            </div>
          </div>
        </body>
      </html>
    `);
  });

  req.pipe(proxyReq, { end: true });
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`[AI Studio Dev Bridge] Port ${PORT} active -> Reverse proxying to PHP 8.2 on 127.0.0.1:8080`);
});
