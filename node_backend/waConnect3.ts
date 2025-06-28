import { Boom } from '@hapi/boom';
import * as baileys from '@whiskeysockets/baileys';
import { deepmerge } from 'deepmerge-ts';
import NodeCache from 'node-cache';
import pino from 'pino';
import qrcode from 'qrcode-terminal';
import readline from 'readline';
import { InMemoryStore } from './MemoryStore';

const logger = pino({
  timestamp: () => `,"time":"${new Date().toJSON()}"`
}).child({});
logger.level = 'silent';

const useStore = !process.argv.includes('--no-store');
const usePairingCode = process.argv.includes('--use-pairing-code');
const msgRetryCounterCache = new NodeCache();

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});
const question = (text: string): Promise<string> => new Promise((resolve) => rl.question(text, resolve));

const store = useStore ? new InMemoryStore() : undefined;
store?.readFromFile('./baileys_auth_info/baileys_store_multi.json');
setInterval(() => store?.writeToFile('./baileys_auth_info/baileys_store_multi.json'), 10000);

export async function connectSocket(config?: baileys.UserFacingSocketConfig): Promise<baileys.WASocket> {
  const { state, saveCreds } = await baileys.useMultiFileAuthState('baileys_auth_info');
  const { version, isLatest } = await baileys.fetchLatestBaileysVersion();
  const defaultConfig: baileys.UserFacingSocketConfig = {
    version,
    logger,
    browser: ['Safari (Linux)', 'browser', '1.0.0'],
    auth: {
      creds: state.creds,
      keys: baileys.makeCacheableSignalKeyStore(state.keys, logger)
    },
    msgRetryCounterCache,
    generateHighQualityLinkPreview: true,
    patchMessageBeforeSending,
    getMessage
  };

  const sock = baileys.makeWASocket(deepmerge(defaultConfig, config));

  store?.bind(sock.ev);

  sock.ev.on('chats.upsert', () => {
    console.log('got chats', store?.chats?.all?.());
  });

  if (usePairingCode && !sock.authState.creds.registered) {
    const phoneNumber = await question('Masukkan nomor telepon seluler anda: +');
    if (/\d/.test(phoneNumber)) {
      const formatted = phoneNumber.replace(/\D/g, '');
      const code = await sock.requestPairingCode(formatted);
      console.log('Jika ada notifikasi WhatsApp [Memasukkan kode menautkan perangkat baru], maka berhasil!');
      console.log(`pairing code: ${code.match(/.{1,4}/g)?.join('-')}`);
    } else {
      console.log('Nomor telepon tidak valid.');
      process.exit(1);
    }
  }

  const simpleEvents = [
    'message-receipt.update',
    'messages.reaction',
    'presence.update',
    'chats.update',
    'labels.association',
    'labels.edit',
    'chats.delete'
  ] as const;

  for (const event of simpleEvents) {
    sock.ev.on(event, (data) => {
      console.log(`${event}:`, data);
    });
  }

  sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      console.log('QR Code:', qr);
      qrcode.generate(qr, { small: true });
    }

    if (connection === 'close') {
      const statusCode = new Boom(lastDisconnect?.error)?.output?.statusCode;
      const reason = baileys.DisconnectReason;

      if (statusCode === reason.badSession) {
        console.log('Bad Session File, hapus session dan scan ulang.');
        process.exit(1);
      }

      if (
        statusCode === reason.connectionClosed ||
        statusCode === reason.connectionLost ||
        statusCode === reason.connectionReplaced ||
        statusCode === reason.restartRequired ||
        statusCode === reason.timedOut
      ) {
        console.log('Koneksi terputus, mencoba ulang...');
        connectSocket();
        return;
      }

      if (statusCode === reason.loggedOut || statusCode === reason.multideviceMismatch) {
        console.log('Perangkat logout atau mismatch, scan ulang.');
        process.exit(1);
      }

      console.log('Terputus, alasan tidak diketahui.');
    }

    if (connection === 'connecting') {
      console.log(`using WA v${version.join('.')}, isLatest ${isLatest}`);
    }

    if (connection === 'open') {
      console.log('Nama:', sock.user?.name);
      console.log('Nomor:', sock.user?.id?.split(':')[0]);
      rl.close();
    }
  });

  // history received
  sock.ev.on('messaging-history.set', (events) => {
    const { chats, contacts, messages, isLatest, progress, syncType } = events;
    if (syncType === baileys.proto.HistorySync.HistorySyncType.ON_DEMAND) {
      console.log('received on-demand history sync, messages=', messages);
    }
    console.log(
      `recv ${chats.length} chats, ${contacts.length} contacts, ${messages.length} msgs (is latest: ${isLatest}, progress: ${progress}%), type: ${syncType}`
    );
  });

  sock.ev.on('creds.update', saveCreds);

  // new messages received
  sock.ev.on('messages.upsert', ({ messages, type }) => {
    console.log('recv messages ', JSON.stringify({ messages, type }, null, 2));
  });

  // messages updated like status delivered, message deleted etc.
  sock.ev.on('messages.update', async (events) => {
    console.log(JSON.stringify(events, undefined, 2));

    for (const { key: _userInfo, update } of events) {
      if (update.pollUpdates) {
        // Try to get the poll creation message from the store
        const pollCreation = await getMessage(_userInfo);
        if (pollCreation && pollCreation.pollCreationMessage) {
          console.log(
            'got poll update, aggregation: ',
            baileys.getAggregateVotesInPollMessage({
              message: pollCreation,
              pollUpdates: update.pollUpdates
            })
          );
        }
      }
    }
  });

  sock.ev.on('contacts.update', async (update) => {
    for (const contact of update || []) {
      if (typeof contact.imgUrl !== 'undefined') {
        const newUrl = contact.imgUrl === null ? null : await sock.profilePictureUrl(contact.id!).catch(() => null);
        console.log(`contact ${contact.id} has a new profile pic: ${newUrl}`);
        if (newUrl) {
          (contact as typeof contact & { imgUrlResolved?: string | null }).imgUrlResolved = newUrl;
        }
      }
      const id = decodeJid(contact.id ?? '');
      if (store?.contacts) {
        store.contacts[id] = {
          id,
          name: contact.notify
        };
      }
    }
    if (update?.length) {
      console.log('contacts update', update);
    }
  });

  sock.ev.on('group-participants.update', ({ id, participants, action }) => {
    console.log('group participants update', { id, participants, action });
  });

  return sock;
}

export async function reply(
  sock: ReturnType<typeof baileys.makeWASocket>,
  from: string,
  content: string,
  message: baileys.proto.IWebMessageInfo
) {
  await sock.sendMessage(from, { text: content }, { quoted: message });
}

function patchMessageBeforeSending(message: any): any {
  if (message.buttonsMessage || message.templateMessage || message.listMessage) {
    return {
      viewOnceMessage: {
        message: {
          messageContextInfo: {
            deviceListMetadataVersion: 2,
            deviceListMetadata: {}
          },
          ...message
        }
      }
    };
  }
  return message;
}

async function getMessage(key: baileys.proto.IMessageKey): Promise<baileys.proto.IMessage | undefined> {
  const messages = store?.messages?.[key.remoteJid!] || [];
  const msg = messages.find((m: baileys.proto.IWebMessageInfo) => m.key?.id === key.id);
  return msg?.message || undefined;
}

function decodeJid(jid: string): string {
  if (!jid) return jid;
  if (/:\d+@/gi.test(jid)) {
    const decode = baileys.jidDecode(jid);
    return decode?.user && decode?.server ? `${decode.user}@${decode.server}` : jid;
  }
  return jid;
}
