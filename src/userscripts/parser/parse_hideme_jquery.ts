/**
 * Function to parse HideMe proxy data.
 * @returns A promise that resolves with an array of proxy data objects.
 */
export const parse_hideme = (): Promise<any[]> => {
  return new Promise((resolve) => {
    const result: { raw: string }[] = [];

    const rows = document.querySelectorAll('.table_block > table > tbody > tr');

    rows.forEach((row) => {
      const tdList = row.querySelectorAll('td');

      const host = tdList[0]?.textContent?.trim();
      const port = tdList[1]?.textContent?.trim();

      if (host && port) {
        result.push({ raw: `${host}:${port}` });
      }
    });

    resolve(result);
  });
};
