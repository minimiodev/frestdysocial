@echo off
echo Adding Vercel environment variables for Frest...

echo postgresql://postgres.rokmiiisjuowipexrxws:Hello%%4012389vn@aws-0-ap-northeast-2.pooler.supabase.com:6543/postgres?pgbouncer=true| npx vercel env add DATABASE_URL production
echo postgresql://postgres:Hello%%4012389vn@db.rokmiiisjuowipexrxws.supabase.co:5432/postgres| npx vercel env add DIRECT_URL production
echo https://rokmiiisjuowipexrxws.supabase.co| npx vercel env add NEXT_PUBLIC_SUPABASE_URL production
echo sb_publishable_l865FU2pbExRdyqe9NdGaQ_9ODL2_nY| npx vercel env add NEXT_PUBLIC_SUPABASE_ANON_KEY production
echo frest_jwt_secure_secret_5f81e3a2c4e97a1b0d2c5e6f| npx vercel env add JWT_SECRET production
echo smtp.gmail.com| npx vercel env add SMTP_HOST production
echo 465| npx vercel env add SMTP_PORT production
echo dungflows@gmail.com| npx vercel env add SMTP_USER production
echo cbui qthd uukc aypv| npx vercel env add SMTP_PASS production
echo dungflows@gmail.com| npx vercel env add SMTP_FROM production

echo Done! All environment variables added.
