import OpenAI from 'openai';
import dotenv from 'dotenv';

dotenv.config({ quiet: true, override: true });

const client = new OpenAI({
  baseURL: 'http://localhost:11434/v1',
  apiKey: 'ollama' // required placeholder only
});

/**
 * MCP stdout must be JSON only
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
    model: process.env.OLLAMA_MODEL || 'llama3',
    messages: [{ role: 'user', content: prompt }],
    temperature: 0.7
  });

  return res.choices[0].message.content;
}

// ----------------------
// TOOLS
// ----------------------
const tools = {
  chat: {
    name: 'chat',
    description: 'Chat with local LLaMA via Ollama',
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
// STATE
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
    } catch {
      error(null, -32700, 'Parse error');
      continue;
    }

    const { id, method, params } = msg;

    log('REQ:', method);

    // ----------------------
    // INITIALIZE
    // ----------------------
    if (method === 'initialize') {
      send({
        jsonrpc: '2.0',
        id,
        result: {
          protocolVersion: '2024-11-05',
          capabilities: { tools: {} },
          serverInfo: {
            name: 'ollama-ai',
            version: '1.0.0'
          }
        }
      });

      initialized = true;

      send({
        jsonrpc: '2.0',
        method: 'notifications/initialized'
      });

      continue;
    }

    if (!initialized && method !== 'initialize') {
      error(id, -32002, 'Not initialized');
      continue;
    }

    // ----------------------
    // LIST TOOLS
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
    // CALL TOOL
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

    error(id, -32601, `Method not found: ${method}`);
  }
});
