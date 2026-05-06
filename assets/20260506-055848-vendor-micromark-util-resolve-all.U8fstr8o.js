function e(e,n,t){const l=[];let o=-1;for(;++o<e.length;){const r=e[o].resolveAll;r&&!l.includes(r)&&(n=r(n,t),l.push(r))}return n}export{e as r};
