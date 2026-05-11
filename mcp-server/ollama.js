import OpenAI from 'openai';
import dotenv from 'dotenv';

dotenv.config({
  quiet: true,
  override: true
});

/**
 * Ollama uses an OpenAI-compatible API.
 * API key is not required for local Ollama,
 * but OpenAI SDK requires a value.
 */
const client = new OpenAI({
  baseURL: 'http://localhost:11434/v1',
  apiKey: process.env.OLLAMA_API_KEY || 'ollama'
});

/**
 * MCP stdout MUST contain JSON ONLY.
 * Any logs MUST go to stderr.
 */
function log(...args) {
  console.error('[MCP]', ...args);
}

function send(payload) {
  process.stdout.write(`${JSON.stringify(payload)}\n`);
}

function sendError(id, code, message, data) {
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
// MODEL CONFIG
// ----------------------
const MODEL = process.env.OLLAMA_MODEL || 'deepseek-r1:latest';

// ----------------------
// TOOL IMPLEMENTATION
// ----------------------
async function chat({ prompt }) {
  if (!prompt || typeof prompt !== 'string') {
    throw new Error('prompt must be a non-empty string');
  }

  log('MODEL:', MODEL);
  log('PROMPT:', prompt);

  const response = await client.chat.completions.create({
    model: MODEL,
    messages: [
      {
        role: 'user',
        content: prompt
      }
    ],
    temperature: 0.7
  });

  return response.choices?.[0]?.message?.content || '';
}

// ----------------------
// TOOLS REGISTRY
// ----------------------
const tools = {
  chat: {
    name: 'chat',
    description: 'Chat with local Ollama AI models',
    inputSchema: {
      type: 'object',
      properties: {
        prompt: {
          type: 'string',
          description: 'Prompt to send to the AI model'
        }
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
// MCP STDIO LOOP
// ----------------------
process.stdin.on('data', async (chunk) => {
  buffer += chunk;

  const lines = buffer.split('\n');
  buffer = lines.pop();

  for (const line of lines) {
    if (!line.trim()) {
      continue;
    }

    let message;

    try {
      message = JSON.parse(line);
    } catch {
      sendError(null, -32700, 'Parse error');
      continue;
    }

    const { id, method, params } = message;

    log('REQUEST:', method);

    // ----------------------
    // INITIALIZE
    // ----------------------
    if (method === 'initialize') {
      initialized = true;

      send({
        jsonrpc: '2.0',
        id,
        result: {
          protocolVersion: '2024-11-05',
          capabilities: {
            tools: {}
          },
          serverInfo: {
            name: 'ollama-ai',
            version: '1.0.0'
          }
        }
      });

      // Optional MCP notification
      send({
        jsonrpc: '2.0',
        method: 'notifications/initialized'
      });

      continue;
    }

    // ----------------------
    // REQUIRE INITIALIZATION
    // ----------------------
    if (!initialized) {
      sendError(id, -32002, 'Server not initialized');
      continue;
    }

    // ----------------------
    // TOOLS LIST
    // ----------------------
    if (method === 'tools/list') {
      send({
        jsonrpc: '2.0',
        id,
        result: {
          tools: Object.values(tools).map((tool) => ({
            name: tool.name,
            description: tool.description,
            inputSchema: tool.inputSchema
          }))
        }
      });

      continue;
    }

    // ----------------------
    // TOOL CALL
    // ----------------------
    if (method === 'tools/call') {
      const toolName = params?.name;
      const toolArgs = params?.arguments ?? {};

      const tool = tools[toolName];

      if (!tool) {
        sendError(id, -32601, `Unknown tool: ${toolName}`);
        continue;
      }

      try {
        const result = await tool.handler(toolArgs);

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
      } catch (error) {
        sendError(id, -32000, error instanceof Error ? error.message : 'Tool execution failed');
      }

      continue;
    }

    // ----------------------
    // UNKNOWN METHOD
    // ----------------------
    sendError(id, -32601, `Method not found: ${method}`);
  }
});

// ----------------------
// PROCESS EVENTS
// ----------------------
process.on('uncaughtException', (error) => {
  console.error('[UNCAUGHT EXCEPTION]', error);
});

process.on('unhandledRejection', (error) => {
  console.error('[UNHANDLED REJECTION]', error);
});

log(`Ollama MCP server started using model: ${MODEL}`);
