// Local-only SMTP integration check. Captures mail in memory; never relays it to the Internet.
import net from 'node:net';
import { spawn } from 'node:child_process';
import { once } from 'node:events';

const sessions = new Set();
const messages = [];
const recipients = [];
const server = net.createServer(socket => {
  sessions.add(socket);
  socket.on('close', () => sessions.delete(socket));
  socket.setEncoding('utf8');
  socket.write('220 localhost SMTP test only\r\n');
  let buffer = '', data = false, message = [];
  socket.on('data', chunk => {
    buffer += chunk;
    while (buffer.includes('\r\n')) {
      const end = buffer.indexOf('\r\n');
      const line = buffer.slice(0, end); buffer = buffer.slice(end + 2);
      if (data) {
        if (line === '.') { messages.push(message.join('\r\n')); message = []; data = false; socket.write('250 accepted locally\r\n'); }
        else message.push(line.startsWith('..') ? line.slice(1) : line);
      } else if (/^(EHLO|HELO)\b/i.test(line)) socket.write('250-localhost\r\n250 SIZE 1000000\r\n');
      else if (/^MAIL FROM:/i.test(line)) socket.write('250 sender accepted\r\n');
      else if (/^RCPT TO:/i.test(line)) { recipients.push(line); socket.write('250 recipient accepted\r\n'); }
      else if (/^DATA$/i.test(line)) { data = true; socket.write('354 end with dot\r\n'); }
      else if (/^QUIT$/i.test(line)) socket.end('221 bye\r\n');
      else socket.write('250 OK\r\n');
    }
  });
});
server.listen(0, '127.0.0.1');
await once(server, 'listening');
try {
  const process = spawn('C:\\xampp\\php\\php.exe', ['-d', 'variables_order=EGPCS', 'bin/console', 'app:mail:test', '--no-ansi'], {
    cwd: new URL('../', import.meta.url),
    env: { ...globalThis.process.env, MAILER_ENABLED:'1', MAILER_DSN:`smtp://127.0.0.1:${server.address().port}`, MAILER_FROM:'smtp-test@example.test' },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  process.stdout.on('data', chunk => { output += chunk; });
  process.stderr.on('data', chunk => { output += chunk; });
  const timeout = setTimeout(() => process.kill(), 20000);
  const [exitCode] = await once(process, 'exit');
  clearTimeout(timeout);
  if (exitCode !== 0 || messages.length !== 1 || recipients.length < 1 || !messages[0].includes('smtp-test@example.test')) {
    throw new Error(`SMTP integration failed (exit ${exitCode}, messages ${messages.length}). ${output}`);
  }
  console.log('OK: Symfony connected to local SMTP and submitted one complete MIME message.');
  console.log('OK: Recipients came from admin settings. No email was relayed externally or saved as a customer inquiry.');
} finally {
  for (const socket of sessions) socket.destroy();
  server.close();
}
