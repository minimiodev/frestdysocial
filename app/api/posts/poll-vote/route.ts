import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để bình chọn." }, { status: 401 });
    }

    const { pollId, optionId } = await req.json();

    if (!pollId || !optionId) {
      return NextResponse.json({ error: "Thiếu thông tin cuộc bình chọn hoặc tùy chọn." }, { status: 400 });
    }

    const parsedPollId = parseInt(pollId);
    const parsedOptionId = parseInt(optionId);

    if (isNaN(parsedPollId) || isNaN(parsedOptionId)) {
      return NextResponse.json({ error: "Dữ liệu không hợp lệ." }, { status: 400 });
    }

    // Kiểm tra xem poll và option có tồn tại và khớp với nhau không
    const pollOption = await db.pollOption.findFirst({
      where: {
        id: parsedOptionId,
        pollId: parsedPollId,
      },
    });

    if (!pollOption) {
      return NextResponse.json({ error: "Tùy chọn bình chọn không hợp lệ." }, { status: 404 });
    }

    // Thực hiện upsert: nếu đã vote poll này rồi thì cập nhật optionId, chưa thì tạo mới
    const vote = await db.pollVote.upsert({
      where: {
        pollId_userId: {
          pollId: parsedPollId,
          userId: user.id,
        },
      },
      update: {
        optionId: parsedOptionId,
      },
      create: {
        pollId: parsedPollId,
        optionId: parsedOptionId,
        userId: user.id,
      },
    });

    return NextResponse.json({ message: "Bình chọn thành công!", vote }, { status: 200 });
  } catch (error: any) {
    console.error("Poll Vote Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
