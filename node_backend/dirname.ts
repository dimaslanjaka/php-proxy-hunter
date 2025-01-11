export const getDirname = () => {
  // get the stack
  const { stack } = new Error();
  // get the third line (the original invoker)
  const invokeFileLine = (stack || '').split(`\n`)[2];
  // match the file url from file://(.+.(ts|js)) and get the first capturing group
  const __filename = (invokeFileLine.match(/file:\/\/(.+.(ts|js))/) || [])[1].slice(1);
  // match the file URL from file://(.+)/ and get the first capturing group
  //     the (.+) is a greedy quantifier and will make the RegExp expand to the largest match
  const __dirname = (invokeFileLine.match(/file:\/\/(.+)\//) || [])[1].slice(1);
  return { __dirname, __filename };
};
export default getDirname;
