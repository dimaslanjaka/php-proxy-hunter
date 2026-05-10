import { spawn } from 'child_process';

const proc = spawn('node', ['mcp-server/index.js'], {
  stdio: ['pipe', 'pipe', 'pipe']
});

// show server logs (VERY IMPORTANT)
proc.stdout.on('data', (data) => {
  console.log('[MCP RESPONSE]', data.toString());
});

proc.stderr.on('data', (data) => {
  console.log('[MCP ERROR]', data.toString());
});

// wait a bit before sending request
setTimeout(() => {
  const msg = {
    id: '1',
    tool: 'chat',
    input: {
      prompt: 'Say hello in one short sentence'
    }
  };

  console.log('[SEND]', msg);

  proc.stdin.write(JSON.stringify(msg) + '\n');
}, 500);
