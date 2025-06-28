// in-memory-store.ts
import { Contact, WAMessage } from '@whiskeysockets/baileys';
import * as fs from 'fs';
import * as path from 'path';

type ChatID = string;

export class InMemoryStore {
  public chats: Record<ChatID, any> = {};
  public messages: Record<ChatID, WAMessage[]> = {};
  public contacts: Record<string, Contact> = {};

  constructor(params?: {
    chats?: Record<ChatID, any>;
    messages?: Record<ChatID, WAMessage[]>;
    contacts?: Record<string, Contact>;
    logger?: any;
  }) {
    if (params) {
      this.chats = params.chats || {};
      this.messages = params.messages || {};
      this.contacts = params.contacts || {};
    }
  }

  /** Store a message */
  saveMessage(jid: string, message: WAMessage) {
    if (!this.messages[jid]) {
      this.messages[jid] = [];
    }
    // Check if message is a WAMessage by checking for expected properties
    if ('key' in message && 'message' in message) {
      this.messages[jid].push(message as WAMessage);
      return message;
    } else {
      //
    }
  }

  /** Get all messages for a chat */
  getMessages(jid: string): WAMessage[] {
    return this.messages[jid] || [];
  }

  /** Clear all messages for a chat */
  clearMessages(jid: string) {
    delete this.messages[jid];
  }

  /** Export to JSON */
  toJSON() {
    return {
      chats: this.chats,
      contacts: this.contacts,
      messages: this.messages
    };
  }

  /** Write store data to a JSON file */
  writeToFile(filePath: string) {
    const dir = path.dirname(filePath);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    fs.writeFileSync(filePath, JSON.stringify(this.toJSON(), null, 2), 'utf-8');
  }

  /** Read store data from a JSON file */
  readFromFile(filePath: string) {
    if (!fs.existsSync(filePath)) return;

    const raw = fs.readFileSync(filePath, 'utf-8');
    try {
      const data = JSON.parse(raw);
      this.chats = data.chats || {};
      this.contacts = data.contacts || {};
      this.messages = data.messages || {};
    } catch (err) {
      console.error(`Failed to parse store file ${filePath}:`, err);
    }
  }

  bind(_obj: any) {
    // Optional binding method for interface compatibility
  }
}
