function r(l){let t=1;const o=[];for(const e of l)e.level>t+1&&o.push({from:t,to:e.level,text:e.text}),t=e.level;return{bodyH1Count:l.filter(e=>e.level===1).length,skipped:o}}export{r as h};
