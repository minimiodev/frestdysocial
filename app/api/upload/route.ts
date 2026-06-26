import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { uploadToR2 } from "@/lib/r2";

export const dynamic = "force-dynamic";

export async function POST(req: NextRequest) {
  try {
    // 1. Xác thực người dùng
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để tải tệp lên." }, { status: 401 });
    }

    // 2. Đọc file từ Form Data
    const formData = await req.formData();
    const file = formData.get("file") as File | null;

    if (!file) {
      return NextResponse.json({ error: "Không tìm thấy tệp tin nào." }, { status: 400 });
    }

    // Giới hạn dung lượng tệp tin (ví dụ: 10MB)
    const MAX_SIZE = 10 * 1024 * 1024; // 10MB
    if (file.size > MAX_SIZE) {
      return NextResponse.json({ error: "Tệp tin vượt quá giới hạn 10MB." }, { status: 400 });
    }

    // 3. Đọc dữ liệu Buffer của file
    const arrayBuffer = await file.arrayBuffer();
    const buffer = Buffer.from(arrayBuffer);

    // 4. Tạo khóa (key) file an toàn duy nhất trên R2
    const originalName = file.name || "media";
    const extension = originalName.split(".").pop() || "bin";
    const cleanName = originalName
      .replace(/[^a-zA-Z0-9]/g, "-")
      .substring(0, 30);
    const fileKey = `uploads/${user.id}-${Date.now()}-${cleanName}.${extension}`;

    // 5. Upload lên Cloudflare R2
    const fileUrl = await uploadToR2(buffer, fileKey, file.type);

    return NextResponse.json({
      message: "Tải tệp lên thành công!",
      url: fileUrl,
      key: fileKey,
      filename: file.name,
      contentType: file.type,
    }, { status: 200 });

  } catch (error: any) {
    console.error("Upload Media Error:", error);
    return NextResponse.json({ error: "Lỗi tải tệp tin lên Cloudflare R2." }, { status: 500 });
  }
}
