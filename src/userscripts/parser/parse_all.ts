import { extractProxies } from '../../proxy/extractor';
import { extractIpPortFromBody } from './extractIpPortFromBody';
import { freeProxySale } from './freeProxySale';
import { parse_first_and_second_row } from './parse_first_and_second_row';
import { parse_first_row_ip_port } from './parse_first_row_ip_port';
import { parse_hideme } from './parse_hideme_jquery';
import { parse_prem_proxy } from './parse_prem_proxy';
import { parse_proxy_db_net } from './parse_proxy_db_net';
import { parse_proxylistplus } from './parse_proxylistplus';
import { parse_second_and_third_row } from './parse_second_and_third_row';

/**
 * Parses proxy information from multiple sources.
 * Returns a promise that resolves with a string containing valid IP:PORT combinations.
 *
 * @returns A promise that resolves with a string of valid proxy addresses.
 */
export const parse_all = function (): Promise<string> {
  return new Promise<string>(function (resolve) {
    /**
     * @type {Promise<{ raw: string }[]>[]}
     */
    const all = [
      freeProxySale(),
      parse_first_and_second_row(),
      parse_hideme(),
      parse_first_row_ip_port(),
      parse_second_and_third_row(),
      parse_proxylistplus(),
      parse_prem_proxy(),
      parse_proxy_db_net(),
      extractIpPortFromBody()
    ];
    Promise.all(all)
      .then(function (results: any[]) {
        const flat: any[] = results.flat().filter(function (item: any) {
          if (!item) return false;
          const str = typeof item === 'string' ? item : JSON.stringify(item);
          const regex = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
          return regex.test(str);
        });

        const additionalItems: Array<{ raw: string }> = [];
        const mappedItems: Array<{ raw: string; ip?: string }> = flat.map(function (item: any) {
          let valid = false;
          const regex_ip = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/gm;
          const regex_port = /(\d{1,5})/gm;
          const regex_proxy = /(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})/gm;
          if (typeof item === 'object') {
            if (item.raw) {
              valid = regex_proxy.test(item.raw);
            }
            if (!valid) {
              if (item.ip) {
                if (regex_proxy.test(item.ip)) {
                  item.raw = item.ip;
                  item.ip = item.raw.split(':')[0];
                }
              }
            }
            let no_more_than_21 = false;
            if (item.raw.length > 21) {
              no_more_than_21 = true;
              const extract = extractProxies(item.raw);
              if (extract.length > 0) {
                for (let i = 0; i < extract.length; i++) {
                  const ex = extract[i];
                  if (i === 0) {
                    item.raw = ex.proxy;
                  } else {
                    additionalItems.push({ raw: ex.proxy || '' });
                  }
                }
              }
            }
            if (item.raw && !no_more_than_21) {
              const split = item.raw.split(':');
              const build_proxy: string[] = [];
              if (split.length > 1) {
                split.forEach(function (str: string) {
                  if (regex_ip.test(str)) {
                    build_proxy[0] = str;
                  } else if (regex_port.test(str)) {
                    build_proxy[1] = str;
                  }
                });
                if (regex_proxy.test(build_proxy.join(':'))) {
                  item.raw = build_proxy.join(':');
                } else if (!regex_proxy.test(item.raw)) {
                  console.error(item.raw, 'invalid regex_proxy');
                  return { raw: '' };
                }
              }
            }
          }
          return item;
        });

        const filteredItems = mappedItems.filter(function (item: { raw: string }) {
          return item && item.raw.length > 0 && item.raw.length <= 21;
        });

        const uniqueItems: Array<{ raw: string }> = filteredItems.concat(additionalItems).filter(function (
          obj: { raw: string },
          index: number,
          self: Array<{ raw: string }>
        ) {
          return (
            index ===
            self.findIndex(function (t) {
              return t.raw === obj.raw;
            })
          );
        });

        let build = '';
        for (let i = 0; i < uniqueItems.length; i++) {
          const item = uniqueItems[i];
          if (build.indexOf(item.raw) === -1) {
            build += item.raw + '\n';
          }
        }

        resolve(build);
      })
      .catch(function (error) {
        console.error(error);
        resolve('<empty proxies>');
      });
  });
};
