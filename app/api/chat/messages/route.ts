import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export const dynamic = "force-dynamic";

/**
 * GET: Lấy lịch sử tin nhắn
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const url = new URL(req.url);
    const receiverId = parseInt(url.searchParams.get("receiverId") || "0");
    const receiverType = url.searchParams.get("receiverType") || "user"; // "user" or "group"

    if (receiverId === 0) {
      return NextResponse.json({ error: "Thiếu ID người nhận/nhóm." }, { status: 400 });
    }

    let messages: any[] = [];

    if (receiverType === "user") {
      // Lấy tin nhắn giữa user hiện tại và receiverId
      messages = await db.message.findMany({
        where: {
          OR: [
            { senderId: user.id, receiverId: receiverId, receiverType: "user" },
            { senderId: receiverId, receiverId: user.id, receiverType: "user" },
          ],
        },
        orderBy: { createdAt: "asc" },
      });

      // Đánh dấu các tin nhắn đối phương gửi cho mình là ĐÃ ĐỌC
      await db.message.updateMany({
        where: {
          senderId: receiverId,
          receiverId: user.id,
          receiverType: "user",
          isRead: false,
        },
        data: { isRead: true },
      });
    } else if (receiverType === "group") {
      // Kiểm tra xem user có phải là member của group không
      const member = await db.chatGroupMember.findFirst({
        where: { groupId: receiverId, userId: user.id },
      });

      if (!member) {
        return NextResponse.json({ error: "Bạn không phải thành viên nhóm này." }, { status: 403 });
      }

      // Lấy tin nhắn của group
      messages = await db.message.findMany({
        where: {
          receiverId: receiverId,
          receiverType: "group",
        },
        orderBy: { createdAt: "asc" },
      });
    }

    return NextResponse.json({ messages });
  } catch (error) {
    console.error("Get Messages Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Gửi tin nhắn mới
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const { receiverId, receiverType, messageText, mediaFilename, mediaType } = await req.json();

    if (!receiverId) {
      return NextResponse.json({ error: "Thiếu thông tin người nhận." }, { status: 400 });
    }

    if (!messageText && !mediaFilename) {
      return NextResponse.json({ error: "Nội dung tin nhắn trống." }, { status: 400 });
    }

    const recId = parseInt(receiverId);

    // Kiểm tra nhóm nếu gửi vào nhóm
    if (receiverType === "group") {
      const isMember = await db.chatGroupMember.findFirst({
        where: { groupId: recId, userId: user.id },
      });
      if (!isMember) {
        return NextResponse.json({ error: "Bạn không thuộc nhóm này." }, { status: 403 });
      }
    }

    // Tạo tin nhắn mới
    const message = await db.message.create({
      data: {
        senderId: user.id,
        senderType: "user", // Có thể mở rộng lấy theo identity hiện tại
        receiverId: recId,
        receiverType: receiverType || "user",
        messageText: messageText || null,
        mediaFilename: mediaFilename || null,
        mediaType: mediaType || null,
        isRead: false,
      },
    });

    // Tạo thông báo nếu gửi cá nhân
    if (receiverType === "user" && recId !== user.id) {
      await db.notification.create({
        data: {
          userId: recId,
          senderId: user.id,
          type: "chat",
          targetId: message.id,
        }
      });
    }

    return NextResponse.json({ message: "Gửi tin nhắn thành công!", data: message }, { status: 201 });
  } catch (error) {
    console.error("Send Message Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
