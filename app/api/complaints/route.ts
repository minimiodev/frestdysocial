import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { uploadToR2 } from "@/lib/r2";

export const dynamic = "force-dynamic";

export async function POST(req: NextRequest) {
  try {
    const formData = await req.formData();
    const reporterName = formData.get("reporterName") as string | null;
    const reporterEmail = formData.get("reporterEmail") as string | null;
    const reporterPhone = formData.get("reporterPhone") as string | null;
    const postUrl = formData.get("postUrl") as string | null;
    const description = formData.get("description") as string | null;
    const file = formData.get("evidence") as File | null;

    if (!reporterName || !reporterName.trim() ||
        !reporterEmail || !reporterEmail.trim() ||
        !postUrl || !postUrl.trim() ||
        !description || !description.trim()) {
      return NextResponse.json({ error: "Vui lòng điền đầy đủ các thông tin bắt buộc (*)." }, { status: 400 });
    }

    // Validation email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(reporterEmail)) {
      return NextResponse.json({ error: "Địa chỉ email liên hệ không hợp lệ." }, { status: 400 });
    }

    let evidenceUrl = null;

    if (file && file.size > 0) {
      const MAX_SIZE = 10 * 1024 * 1024; // 10MB
      if (file.size > MAX_SIZE) {
        return NextResponse.json({ error: "Tệp minh chứng vượt quá giới hạn 10MB." }, { status: 400 });
      }

      // Đọc file thành buffer
      const arrayBuffer = await file.arrayBuffer();
      const buffer = Buffer.from(arrayBuffer);

      // Tạo key duy nhất trên R2
      const originalName = file.name || "evidence";
      const extension = originalName.split(".").pop() || "bin";
      const cleanName = originalName.replace(/[^a-zA-Z0-9]/g, "-").substring(0, 30);
      const fileKey = `complaints/evidence-${Date.now()}-${cleanName}.${extension}`;

      // Upload lên Cloudflare R2
      evidenceUrl = await uploadToR2(buffer, fileKey, file.type);
    }

    // Lưu khiếu nại bản quyền vào database
    const complaint = await db.copyrightComplaint.create({
      data: {
        reporterName: reporterName.trim(),
        reporterEmail: reporterEmail.trim(),
        reporterPhone: reporterPhone ? reporterPhone.trim() : null,
        postUrl: postUrl.trim(),
        description: description.trim(),
        evidenceFilename: evidenceUrl,
        status: "pending",
      },
    });

    return NextResponse.json({
      message: "Khiếu nại bản quyền của bạn đã được gửi thành công. Ban quản trị sẽ sớm xem xét và xử lý.",
      complaint,
    }, { status: 201 });

  } catch (error: any) {
    console.error("Submit Complaint Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi gửi khiếu nại bản quyền." }, { status: 500 });
  }
}
