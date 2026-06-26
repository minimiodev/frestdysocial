const { execSync } = require('child_process');

const envVars = [
  { name: 'DATABASE_URL', value: 'postgresql://postgres.rokmiiisjuowipexrxws:Hello%4012389vn@aws-0-ap-northeast-2.pooler.supabase.com:6543/postgres?pgbouncer=true' },
  { name: 'DIRECT_URL', value: 'postgresql://postgres:Hello%4012389vn@db.rokmiiisjuowipexrxws.supabase.co:5432/postgres' },
  { name: 'NEXT_PUBLIC_SUPABASE_URL', value: 'https://rokmiiisjuowipexrxws.supabase.co' },
  { name: 'NEXT_PUBLIC_SUPABASE_ANON_KEY', value: 'sb_publishable_l865FU2pbExRdyqe9NdGaQ_9ODL2_nY' },
  { name: 'JWT_SECRET', value: 'frest_jwt_secure_secret_5f81e3a2c4e97a1b0d2c5e6f' },
  { name: 'SMTP_HOST', value: 'smtp.gmail.com' },
  { name: 'SMTP_PORT', value: '465' },
  { name: 'SMTP_USER', value: 'dungflows@gmail.com' },
  { name: 'SMTP_PASS', value: 'cbui qthd uukc aypv' },
  { name: 'SMTP_FROM', value: 'dungflows@gmail.com' },
];

const environments = ['production', 'preview', 'development'];

for (const { name, value } of envVars) {
  for (const env of environments) {
    try {
      // Dùng stdin để truyền value vào vercel env add
      const result = execSync(
        `npx vercel env add ${name} ${env}`,
        { input: value, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }
      );
      console.log(`✅ ${name} [${env}] added`);
    } catch (err) {
      const output = err.stdout || err.stderr || '';
      if (output.includes('already exists') || output.includes('409')) {
        console.log(`⚠️  ${name} [${env}] already exists, skipping`);
      } else {
        console.log(`❌ ${name} [${env}] failed: ${output.trim().substring(0, 100)}`);
      }
    }
  }
}

console.log('\n✅ Done! Triggering redeploy...');
try {
  execSync('npx vercel --prod --yes', { encoding: 'utf8', stdio: 'inherit' });
} catch (e) {
  console.log('Deploy command sent.');
}
