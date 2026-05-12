/**
 * Split a string into chunks of lines.
 * @param {string} input - The input string to split.
 * @param {number} linesPerChunk - Maximum number of lines per chunk.
 * @returns {string[]} An array of chunks, where each chunk is a string with up to `linesPerChunk` lines.
 */
export const splitStringByLines = (input: string, linesPerChunk: number): string[] => {
  // Split the input string by lines
  const lines = input.split('\n');

  // Initialize an array to hold chunks of lines
  const chunks = [];

  // Loop through lines and group into chunks
  for (let i = 0; i < lines.length; i += linesPerChunk) {
    // Slice the array to get the chunk of lines
    const chunk = lines.slice(i, i + linesPerChunk);

    // Join the chunk back into a single string and push it to the array
    chunks.push(chunk.join('\n'));
  }

  return chunks;
};
