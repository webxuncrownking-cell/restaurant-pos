import express from 'express';
import http from 'http';
import { spawn } from 'child_process';

// Ensure MariaDB is started in local container environment
try {
  spawn('/etc/init.d/mariadb', ['start'], { stdio: 'ignore' });
} catch (e) {
  // ignore if already started
}

// Start PHP built-in server on 127.0.0.1:8080 serving pure PHP 8.2 backend
const phpProcess = spawn('php', ['-S', '127.0.0.1:8080', 'index.php'], {
  stdio: 'inherit'
});

process.on('exit', () => {
  phpProcess.kill();
});

const app = express();
const PORT = 3000;

// Reverse proxy all incoming preview traffic directly to PHP application
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
    res.writeHead(502, { 'Content-Type': 'text/plain' });
    res.end('PHP Server Gateway Error: ' + err.message);
  });

  req.pipe(proxyReq, { end: true });
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`[AI Studio Dev Bridge] Port 3000 active -> Reverse proxying to PHP 8.2 on 127.0.0.1:8080`);
});
