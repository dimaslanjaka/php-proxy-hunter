import * as baileys from '@whiskeysockets/baileys';
import fs from 'fs-extra';
import Long from 'long';
import mime from 'mime';
import { promisify } from 'util';
import { productionEnv } from '../.env.mjs';

// let lastSend = new Date();

export default class Replier {
  receivedText?: string | null;
  sender?: baileys.WAProto.IWebMessageInfo;
  private sock?: ReturnType<typeof baileys.makeWASocket>;
  /**
   * sender id ends with @g.us for group, ends with @s.whatsapp.net for personal
   */
  senderId?: string | null;
  isGroup = false;
  /**
   * sender whatsapp name
   */
  senderName: string;
  timestamp?: number | Long.Long | null;
  dateRFC3339: string;
  /**
   * is sender phone number has admin privileges
   */
  isAdmin = false;

  constructor(msg?: baileys.WAProto.IWebMessageInfo, socket?: ReturnType<typeof baileys.makeWASocket>) {
    this.receivedText = msg?.message?.extendedTextMessage?.text || msg?.message?.conversation;
    this.sender = msg;
    this.sock = socket;
    this.senderId = msg?.key.remoteJid;
    this.senderName = msg?.pushName || 'Unknown@s.whatsapp.net';
    this.isGroup = msg?.key.remoteJid?.endsWith('@g.us') || false;
    this.timestamp = msg?.messageTimestamp;
    this.dateRFC3339 = msg && msg.messageTimestamp ? this.convertTimestampToRFC3339(msg.messageTimestamp) : '';
    this.isAdmin = productionEnv.WHATSAPP_ADMIN.some(
      (number_phone) => this.senderId?.startsWith(number_phone) ?? false
    );
  }

  /**
   * reply sender
   * @param text text contents
   */
  async reply(text: string) {
    if (this.sender) {
      // mark message readed
      await this.read(this.sender);
      // replying
      // await this.sendMessageWTyping({ text }, this.sender.key.remoteJid!);
      if (this.sender.key && this.sender.key.remoteJid) {
        await this.sock?.sendMessage(this.sender.key.remoteJid, { text });
      }
    } else {
      const e = new Error();
      const stack = e.stack ?? '';
      let callerLineAndFile = stack
        .split('\n')[2]
        .replace(/at\s+file:\/\/\//, '')
        .trim();
      const regex = /\((.*):(\d+):(\d+)\)$/;
      const match1 = regex.exec(stack.split('\n')[2]);
      const match2 = ''.match(/file:\/\/\/(.+):(\d+):(\d+)/);
      let match: RegExpExecArray | RegExpMatchArray | null = null;
      if (match1) {
        match = match1;
      } else if (match2) {
        match = match2;
      }
      if (match) callerLineAndFile = `${match[1]}:${match[2]}:${match[3]}`;
      console.error(
        `Replier.reply sender field is null or undefined.\n${callerLineAndFile}\nmessage: ${text.substring(0, 100)}`
      );
    }
  }

  /**
   * reply with image
   * @param caption text caption
   * @param file image path
   */
  async replyImage(caption: string, file: string) {
    if (this.sender && this.sock) {
      // mark message readed
      await this.read(this.sender);
      const bitmap = await promisify(fs.readFile)(file);
      // console.log('replying with', file);
      // convert binary data to base64 encoded string
      // const base64 = Buffer.from(bitmap).toString('base64');
      const mimetype = mime.getType(file) || 'image/png';
      // replying
      await this.sock
        .sendMessage(this.sender.key.remoteJid!, {
          image: bitmap,
          // jpegThumbnail: base64,
          caption,
          mimetype
        })
        .catch(console.error);
    }
  }

  /**
   * mark read message
   * @param msg
   */
  read(msg: baileys.WAProto.IWebMessageInfo) {
    return this.sock!.readMessages([msg.key]);
  }

  /**
   * reply with typing indication
   * @param msg
   * @param jid
   */
  async replyWTyping(msg: baileys.AnyMessageContent, jid: string) {
    await this.sock?.presenceSubscribe(jid);
    await baileys.delay(500);

    await this.sock?.sendPresenceUpdate('composing', jid);
    await baileys.delay(2000);

    await this.sock?.sendPresenceUpdate('paused', jid);

    await this.sock?.sendMessage(jid, msg);
  }

  private convertTimestampToRFC3339(timestamp: number | Long) {
    if (timestamp instanceof Long) {
      timestamp = timestamp.toNumber();
    }
    const date = new Date(timestamp * 1000); // Convert to milliseconds
    const options: Intl.DateTimeFormatOptions = {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      timeZoneName: 'short'
    };

    // Format the date in the desired RFC3339 format
    const formattedDate = date.toLocaleString('en-GB', options).replace(',', '').replace(' ', 'T');

    // Adjust the timezone offset to match the format `+0700`
    const offset = -date.getTimezoneOffset();
    const sign = offset >= 0 ? '+' : '-';
    const absOffset = Math.abs(offset);
    const formattedOffset = `${sign}${String(absOffset).padStart(4, '0')}`;

    return formattedDate + formattedOffset;
  }
}
