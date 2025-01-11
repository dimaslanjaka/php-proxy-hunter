import { Boom } from '@hapi/boom';
import type * as baileysType from '@whiskeysockets/baileys';
import * as baileys from '@whiskeysockets/baileys';
import fs from 'fs-extra';
import NodeCache from 'node-cache';
import path from 'path';
import P from 'pino';
import { writefile } from 'sbg-utility';
import { baileyLogFile as console, initDir } from '../Function.js';

initDir('./baileys_auth_info');
initDir('./baileys_auth_info/log');

if (fs.existsSync('./baileys_auth_info/error')) {
  fs.rmSync('./baileys_auth_info', { recursive: true, force: true });
}

const msgRetryCounterCache = new NodeCache();
const logger = P().child({ level: 'silent', stream: 'store' }) as any;
const store = baileys.makeInMemoryStore({ logger });
store.readFromFile('./baileys_auth_info/baileys_store_multi.json');
setInterval(() => {
  store.writeToFile('./baileys_auth_info/baileys_store_multi.json');
}, 10000);

async function connect() {
  const { state, saveCreds } = await baileys.useMultiFileAuthState(path.resolve('./baileys_auth_info'));
  const { version, isLatest } = await baileys.fetchLatestBaileysVersion();
  console.log(`using WA v${version.join('.')}, isLatest: ${isLatest}`);

  const sock = baileys.makeWASocket({
    browser: baileys.Browsers.ubuntu('Desktop'),
    version,
    logger,
    printQRInTerminal: true,
    mobile: false,
    auth: {
      creds: state.creds,
      /** caching makes the store faster to send/recv messages */
      keys: baileys.makeCacheableSignalKeyStore(state.keys, logger)
    },
    msgRetryCounterCache,
    generateHighQualityLinkPreview: true,
    // ignore all broadcast messages -- to receive the same
    // comment the line below out
    // shouldIgnoreJid: jid => isJidBroadcast(jid),
    // implement to handle retries & poll updates
    getMessage
  });

  async function getMessage(key: baileysType.WAMessageKey): Promise<baileysType.WAMessageContent | undefined> {
    if (store) {
      const msg = await store.loadMessage(key.remoteJid!, key.id!);
      return msg?.message || undefined;
    }

    // only if store is present
    return baileys.proto.Message.fromObject({});
  }

  store?.bind(sock.ev);
  sock.ev.process(async (events) => {
    // credentials updated -- save them
    if (events['creds.update']) {
      await saveCreds();
    }
    // something about the connection changed
    // maybe it closed, or we received all offline message or connection opened
    if (events['connection.update']) {
      const update = events['connection.update'];
      const { connection, lastDisconnect } = update;
      if (connection === 'close') {
        console.log('connection.update status code ' + (lastDisconnect?.error as Boom)?.output?.statusCode);
        // reconnect if not logged out
        if ((lastDisconnect?.error as Boom)?.output?.statusCode !== baileys.DisconnectReason.loggedOut) {
          connect();
        } else {
          console.log('Connection closed. You are logged out.');
          sock.ev.removeAllListeners('connection.update');
          sock.ev.removeAllListeners('creds.update');
          sock.ev.removeAllListeners('blocklist.set');
          sock.end(new Error('Connection closed. You are logged out.'));
          // delete folder
          writefile(path.resolve('./baileys_auth_info/error'), 'true');
          // exit
          process.exit(1);
        }
      }

      console.log('connection update', update);
    }

    // history sync, everything is reverse chronologically sorted
    if (events['messaging-history.set']) {
      const { chats, contacts, messages, isLatest } = events['messaging-history.set'];
      console.log(
        `recv ${chats.length} chats, ${contacts.length} contacts, ${messages.length} msgs (is latest: ${isLatest})`
      );
    }

    //inbox
    if (events['messages.upsert']) {
      const upsert = events['messages.upsert'];
      console.log('recv messages ', JSON.stringify(upsert, undefined, 2));

      if (upsert.type === 'notify') {
        for (const msg of upsert.messages) {
          console.log(msg.key);
          if (!msg.key.fromMe) {
            console.log('received message from', msg.key.remoteJid);
          }
        }
      } else {
        console.log(upsert);
      }
    }
  });
}

connect();
