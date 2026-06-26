const https = require('https');

const targetIp = '2406:da12:1f1:f801:7d79:e24e:d9b6:1da4';

https.get('https://ip-ranges.amazonaws.com/ip-ranges.json', (res) => {
  let data = '';
  res.on('data', (chunk) => { data += chunk; });
  res.on('end', () => {
    try {
      const ipRanges = JSON.parse(data);
      const ipv6Prefixes = ipRanges.ipv6_prefixes;
      
      console.log(`Searching for region containing ${targetIp}...`);
      
      // Đơn giản hóa: so sánh prefix
      // Các prefix của AWS thường có dạng 2406:da12:1f1:...
      // Hãy log các prefix khớp với 2406:da12:
      const matches = ipv6Prefixes.filter(item => {
        return item.ipv6_prefix.toLowerCase().startsWith('2406:da12:');
      });
      
      console.log('Matches found:', JSON.stringify(matches, null, 2));
      
    } catch (e) {
      console.error('Error parsing JSON:', e.message);
    }
  });
}).on('error', (e) => {
  console.error('Error fetching data:', e.message);
});
