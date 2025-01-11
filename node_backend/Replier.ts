import * as baileys from '@whiskeysockets/baileys';
import fs from 'fs-extra';
import mime from 'mime';
import { promisify } from 'util';

// let lastSend = new Date();

export default class Replier {
  receivedText: string | null | undefined;
  sender: baileys.WAProto.IWebMessageInfo;
  private sock!: ReturnType<typeof baileys.makeWASocket>;
  senderName: string | null | undefined;

  constructor(msg: baileys.WAProto.IWebMessageInfo, socket: ReturnType<typeof baileys.makeWASocket>) {
    this.receivedText = msg.message?.extendedTextMessage?.text;
    this.sender = msg;
    this.sock = socket;
    this.senderName = msg.key.remoteJid;
  }

  /**
   * reply sender
   * @param text text contents
   */
  async reply(text: string) {
    // mark message readed
    await this.read(this.sender);
    // replying
    // await this.sendMessageWTyping({ text }, this.sender.key.remoteJid!);
    await this.sock.sendMessage(this.sender.key.remoteJid!, { text });
    // lastSend = new Date();
  }

  /**
   * reply with image
   * @param caption text caption
   * @param file image path
   */
  async replyImage(caption: string, file: string) {
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
    await this.sock.presenceSubscribe(jid);
    await baileys.delay(500);

    await this.sock.sendPresenceUpdate('composing', jid);
    await baileys.delay(2000);

    await this.sock.sendPresenceUpdate('paused', jid);

    await this.sock.sendMessage(jid, msg);
  }
}
