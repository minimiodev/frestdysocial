import { S3Client, PutObjectCommand, DeleteObjectCommand } from "@aws-sdk/client-s3";
import { getSignedUrl } from "@aws-sdk/s3-request-presigner";

const s3Client = new S3Client({
  region: "auto",
  endpoint: process.env.R2_ENDPOINT || "",
  credentials: {
    accessKeyId: process.env.R2_ACCESS_KEY_ID || "",
    secretAccessKey: process.env.R2_SECRET_ACCESS_KEY || "",
  },
});

const BUCKET_NAME = process.env.R2_BUCKET_NAME || "frestdy";
const PUBLIC_URL = process.env.NEXT_PUBLIC_R2_PUBLIC_URL || "";

/**
 * Tải trực tiếp buffer từ server Next.js lên Cloudflare R2
 */
export async function uploadToR2(buffer: Buffer, key: string, contentType: string): Promise<string> {
  const command = new PutObjectCommand({
    Bucket: BUCKET_NAME,
    Key: key,
    Body: buffer,
    ContentType: contentType,
  });

  await s3Client.send(command);
  
  // Trả về url truy cập CDN của file
  return `${PUBLIC_URL.replace(/\/$/, "")}/${key}`;
}

/**
 * Xóa file trên Cloudflare R2
 */
export async function deleteFromR2(key: string): Promise<void> {
  const command = new DeleteObjectCommand({
    Bucket: BUCKET_NAME,
    Key: key,
  });

  await s3Client.send(command);
}

/**
 * Tạo Presigned URL cho Client-side upload trực tiếp lên Cloudflare R2 (Tránh quá tải server Next.js)
 */
export async function getPresignedUploadUrl(key: string, contentType: string, expiresInSeconds = 3600): Promise<{ uploadUrl: string; fileUrl: string }> {
  const command = new PutObjectCommand({
    Bucket: BUCKET_NAME,
    Key: key,
    ContentType: contentType,
  });

  const uploadUrl = await getSignedUrl(s3Client, command, { expiresIn: expiresInSeconds });
  const fileUrl = `${PUBLIC_URL.replace(/\/$/, "")}/${key}`;

  return { uploadUrl, fileUrl };
}
