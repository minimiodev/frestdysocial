const { Client } = require('pg');

// Thử kết nối trực tiếp không qua pooler
// Dùng IPv6 address của db host
const configs = [
  {
    name: 'Direct DB - IPv6 explicit',
    host: '2406:da12:1f1:f801:7d79:e24e:d9b6:1da4',
    port: 5432,
    user: 'postgres',
    password: 'Hello@12389vn',
    database: 'postgres',
    ssl: { rejectUnauthorized: false }
  },
  {
    name: 'Pooler Seoul port 5432 session mode',
    host: 'aws-0-ap-northeast-2.pooler.supabase.com',
    port: 5432,
    user: 'postgres.rokmiiisjuowipexrxws',
    password: 'Hello@12389vn',
    database: 'postgres',
    ssl: { rejectUnauthorized: false }
  },
  {
    name: 'Pooler Seoul port 6543 transaction mode - no ssl verify',
    host: 'aws-0-ap-northeast-2.pooler.supabase.com',
    port: 6543,
    user: 'postgres.rokmiiisjuowipexrxws',
    password: 'Hello@12389vn',
    database: 'postgres',
    ssl: false
  },
  {
    name: 'Pooler Seoul port 6543 - ssl required',
    host: 'aws-0-ap-northeast-2.pooler.supabase.com',
    port: 6543,
    user: 'postgres.rokmiiisjuowipexrxws',
    password: 'Hello@12389vn',
    database: 'postgres',
    ssl: { rejectUnauthorized: false, sslmode: 'require' }
  }
];

async function testConfig(config) {
  console.log(`\nTesting: ${config.name}`);
  const client = new Client({
    ...config,
    connectionTimeoutMillis: 10000
  });
  try {
    await client.connect();
    const res = await client.query('SELECT NOW(), current_user, current_database()');
    console.log(`✅ SUCCESS!`);
    console.log(`   Time: ${res.rows[0].now}`);
    console.log(`   User: ${res.rows[0].current_user}`);
    console.log(`   DB: ${res.rows[0].current_database}`);
    await client.end();
    return true;
  } catch (err) {
    console.log(`❌ FAILED: ${err.message}`);
    try { await client.end(); } catch (e) {}
    return false;
  }
}

async function run() {
  console.log('=== Extended Supabase Connection Test ===');
  for (const config of configs) {
    await testConfig(config);
  }
}

run();
