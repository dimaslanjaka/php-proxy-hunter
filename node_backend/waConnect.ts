/* eslint-disable @typescript-eslint/no-unsafe-declaration-merging */
import { Boom } from '@hapi/boom';
import * as baileys from '@whiskeysockets/baileys';
import events from 'events';
import fs from 'fs-extra';
import NodeCache from 'node-cache';
import path from 'path';
import P from 'pino';
import qrcode from 'qrcode-terminal';
import { writefile } from 'sbg-utility';
import { ConsoleFile, FOLDER_CONFIG, FOLDER_LOG, initDir } from './Function.js';
import { InMemoryStore } from './MemoryStore.js';
import Replier from './Replier.js';

export interface waOption {
  [key: string]: any;
  /** session directory */
  base: string;
  /** log directory */
  logDir: string;
}

export default interface waConnect {
  /**
   * inbox event received
   */
  on(event: 'messages', listener: (replier: Replier) => any): this;
  /**
   * sync event received
   */
  on(event: 'sync', listener: (obj: baileys.BaileysEventMap['messaging-history.set']) => any): this;
  on(event: string, listener: (...args: any[]) => any): this;
}

export default class waConnect extends events.EventEmitter {
  private sock!: ReturnType<typeof baileys.makeWASocket>;
  console!: ReturnType<typeof ConsoleFile>;
  msgRetryCounterCache!: NodeCache;
  logger = P().child({ level: 'silent', stream: 'store' }) as any;
  config: waOption = {
    base: FOLDER_CONFIG,
    logDir: FOLDER_LOG
  };
  store?: InMemoryStore;

  constructor(socket?: ReturnType<typeof baileys.makeWASocket>) {
    super();
    if (socket) this.sock = socket;
  }

  setup(config: Partial<waOption> = {}) {
    this.config = Object.assign(this.config, config || {});
    initDir(this.config.base);
    initDir(this.config.logDir);

    // delete session directory when error caught
    if (fs.existsSync(path.join(this.config.base, 'error'))) {
      fs.rmSync(this.config.base, { recursive: true, force: true });
    }

    this.console = ConsoleFile(this.config.logDir);
    this.msgRetryCounterCache = new NodeCache();
    this.store = new InMemoryStore({ logger: this.logger });
    const sessionCoreFile = path.join(this.config.base, 'baileys_store_multi.json');
    this.store.readFromFile(sessionCoreFile);
    // save sessions every 10s
    setInterval(() => {
      this.store?.writeToFile(sessionCoreFile);
    }, 10000);
    return this;
  }

  /**
   * get replier instance for target
   * @param id ex `+6285655667573@s.whatsapp.net`
   * @returns
   */
  getReplier(id: string) {
    return new Replier({ key: { remoteJid: id } }, this.sock);
  }

  async connect() {
    const { state, saveCreds } = await baileys.useMultiFileAuthState(path.resolve(this.config.base));
    const { version, isLatest } = await baileys.fetchLatestBaileysVersion();
    console.log(`using WA v${version.join('.')}, isLatest: ${isLatest}`);

    this.sock = baileys.makeWASocket({
      browser: baileys.Browsers.ubuntu('Desktop'),
      version,
      logger: this.logger,
      mobile: false,
      auth: {
        creds: state.creds,
        /** caching makes the store faster to send/recv messages */
        keys: baileys.makeCacheableSignalKeyStore(state.keys, this.logger)
      },
      msgRetryCounterCache: this.msgRetryCounterCache,
      generateHighQualityLinkPreview: true,
      // ignore all broadcast messages -- to receive the same
      // comment the line below out
      // shouldIgnoreJid: jid => isJidBroadcast(jid),
      // implement to handle retries & poll updates
      getMessage: this.getMessage
    });

    this.store?.bind(this.sock.ev);

    const self = this;

    this.sock.ev.process(async (events) => {
      // credentials updated -- save them
      if (events['creds.update']) {
        await saveCreds();
      }
      // something about the connection changed
      // maybe it closed, or we received all offline message or connection opened
      if (events['connection.update']) {
        const update = events['connection.update'];
        const { connection, lastDisconnect, qr } = update;
        if (qr && qr.length > 0) {
          console.log('QR Code:', qr);
          qrcode.generate(qr);
        }
        if (connection === 'close') {
          if (lastDisconnect) {
            const shouldReconnect =
              (lastDisconnect.error as Boom)?.output?.statusCode !== baileys.DisconnectReason.loggedOut;
            console.log('connection closed due to ', lastDisconnect.error, ', reconnecting ', shouldReconnect);
            // reconnect if not logged out
            if (shouldReconnect) {
              return this.connect();
            }
          }
          console.log('connection.update status code ' + (lastDisconnect?.error as Boom)?.output?.statusCode);
          // reconnect if not logged out
          if ((lastDisconnect?.error as Boom)?.output?.statusCode !== baileys.DisconnectReason.loggedOut) {
            // reconnect
            this.connect();
          } else {
            console.log('Connection closed. You are logged out.');
            this.sock.ev.removeAllListeners('connection.update');
            this.sock.ev.removeAllListeners('creds.update');
            this.sock.ev.removeAllListeners('messaging-history.set');
            this.sock.end(new Error('Connection closed. You are logged out.'));
            // delete session folder to re-login again
            writefile(path.resolve(path.join(this.config.base, 'error')), 'true');
            // exit
            process.exit(1);
          }
        }

        if (update.qr) {
          this.console.log(update);
        }

        // console.log('connection update', update);
      }

      // history sync, everything is reverse chronologically sorted
      if (events['messaging-history.set']) {
        // const { chats, contacts, messages, isLatest } = events['messaging-history.set'];
        // console.log(
        //   `recv ${chats.length} chats, ${contacts.length} contacts, ${messages.length} msgs (is latest: ${isLatest})`
        // );
        self.emit('sync', events['messaging-history.set']);
      }

      // received a new message
      if (events['messages.upsert']) {
        const upsert = events['messages.upsert'];
        // console.log('recv messages ', JSON.stringify(upsert, undefined, 2));

        if (upsert.type === 'notify') {
          for (const msg of upsert.messages) {
            if (!msg.key.fromMe) {
              if (!msg.message) return;
              // console.log('text messages ', msg.message?.extendedTextMessage?.text);
              // console.log('Emitting message event for:', msg.key.remoteJid);
              self.emit('messages', new Replier(msg, this.sock));
              // await this.sock!.readMessages([msg.key]);
              // await this.sock!.sendMessage(msg.key.remoteJid!, { text: 'Hello there!' });
            }
          }
        }
      }
    });
  }

  private async getMessage(key: baileys.WAMessageKey) {
    if (this.store) {
      const msg = this.store.saveMessage(key.remoteJid!, { key });
      return msg?.message || undefined;
    }

    // only if store is present
    return baileys.proto.Message.fromObject({});
  }
}
