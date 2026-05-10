import OpenAI from 'openai';
import dotenv from 'dotenv';

dotenv.config({ quiet: true, override: true });

const client = new OpenAI({
  apiKey: process.env.NVIDIA_API_KEY,
  baseURL: 'https://integrate.api.nvidia.com/v1'
});

/**
 * IMPORTANT:
 * MCP stdout MUST be JSON only.
 * ALL logs go to stderr.
 */
function log(...args) {
  console.error('[MCP]', ...args);
}

function send(payload) {
  process.stdout.write(JSON.stringify(payload) + '\n');
}

function error(id, code, message, data) {
  send({
    jsonrpc: '2.0',
    id: id ?? null,
    error: {
      code,
      message,
      ...(data ? { data } : {})
    }
  });
}

// ----------------------
// TOOL IMPLEMENTATION
// ----------------------
async function chat({ prompt }) {
  if (!prompt || typeof prompt !== 'string') {
    throw new Error('prompt must be a string');
  }

  const res = await client.chat.completions.create({
    model: 'deepseek-ai/deepseek-v4-flash',
    messages: [{ role: 'user', content: prompt }],
    temperature: 0.7
  });

  return res.choices[0].message.content;
}

// ----------------------
// TOOLS REGISTRY
// ----------------------
const tools = {
  chat: {
    name: 'chat',
    description: 'Chat with NVIDIA DeepSeek',
    inputSchema: {
      type: 'object',
      properties: {
        prompt: { type: 'string' }
      },
      required: ['prompt']
    },
    handler: chat
  }
};

// ----------------------
// MCP STATE
// ----------------------
let initialized = false;

process.stdin.setEncoding('utf8');
let buffer = '';

// ----------------------
// MCP LOOP
// ----------------------
process.stdin.on('data', async (chunk) => {
  buffer += chunk;

  const lines = buffer.split('\n');
  buffer = lines.pop();

  for (const line of lines) {
    if (!line.trim()) continue;

    let msg;

    try {
      msg = JSON.parse(line);
    } catch (_e) {
      error(null, -32700, 'Parse error');
      continue;
    }

    const { id, method, params } = msg;

    log('REQ:', method);

    // ----------------------
    // 1. INITIALIZE
    // ----------------------
    if (method === 'initialize') {
      send({
        jsonrpc: '2.0',
        id,
        result: {
          protocolVersion: '2024-11-05',
          capabilities: {
            tools: {}
          },
          serverInfo: {
            name: 'nvidia-ai',
            version: '1.0.0'
          }
        }
      });

      // IMPORTANT: mark initialized internally
      initialized = true;

      // optional notification (safe for Copilot variants)
      send({
        jsonrpc: '2.0',
        method: 'notifications/initialized'
      });

      continue;
    }

    // Reject calls before init
    if (!initialized && method !== 'initialize') {
      error(id, -32002, 'Not initialized');
      continue;
    }

    // ----------------------
    // 2. LIST TOOLS
    // ----------------------
    if (method === 'tools/list') {
      send({
        jsonrpc: '2.0',
        id,
        result: {
          tools: Object.values(tools).map((t) => ({
            name: t.name,
            description: t.description,
            inputSchema: t.inputSchema
          }))
        }
      });

      continue;
    }

    // ----------------------
    // 3. CALL TOOL
    // ----------------------
    if (method === 'tools/call') {
      const name = params?.name;
      const args = params?.arguments ?? {};

      const tool = tools[name];

      if (!tool) {
        error(id, -32601, `Unknown tool: ${name}`);
        continue;
      }

      try {
        const result = await tool.handler(args);

        send({
          jsonrpc: '2.0',
          id,
          result: {
            content: [
              {
                type: 'text',
                text: result
              }
            ]
          }
        });
      } catch (e) {
        error(id, -32000, e.message);
      }

      continue;
    }

    // ----------------------
    // UNKNOWN METHOD
    // ----------------------
    error(id, -32601, `Method not found: ${method}`);
  }
});
