'use strict';
// Team branding shared by the lesson handouts.
const fs = require('fs');
const path = require('path');

const url = 'https://t.me/+5m-10zROu8I4M2Zk';
const TEAM = {
  name: 'KABOSU TEAM',
  url,
  label: url.replace('https://', ''),
  // QR code for the channel link (team-qr.svg, opaque vector modules)
  qr: fs.readFileSync(path.join(__dirname, 'team-qr.svg'), 'utf8').replace('width="33" height="33"', 'viewBox="0 0 33 33" shape-rendering="crispEdges"'),
};

module.exports = { TEAM };
