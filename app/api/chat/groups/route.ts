import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";

/**
 * GET: Lấy toàn bộ nhóm chat mà user hiện tại đang tham gia
 */
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const memberships = await db.chatGroupMember.findMany({
      where: { userId: user.id },
      include: {
        group: {
          include: {
            members: {
              include: {
                user: {
                  select: {
                    id: true,
                    username: true,
                    fullName: true,
                    avatarFilename: true,
                  }
                }
              }
            }
          }
        }
      }
    });

    const groups = memberships.map((m) => m.group);
    return NextResponse.json({ groups });
  } catch (error) {
    console.error("Get Chat Groups Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}

/**
 * POST: Tạo nhóm chat mới
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const { name, description, memberIds } = await req.json(); // memberIds là mảng ID các user được thêm vào

    if (!name || !name.trim()) {
      return NextResponse.json({ error: "Tên nhóm không được để trống." }, { status: 400 });
    }

    const newGroup = await db.$transaction(async (tx) => {
      // 1. Tạo nhóm chat
      const group = await tx.chatGroup.create({
        data: {
          name: name.trim(),
          description: description || null,
          creatorId: user.id,
          creatorType: "user",
          avatarFilename: "group_default.png",
        },
      });

      // 2. Thêm người tạo vào nhóm với quyền admin
      await tx.chatGroupMember.create({
        data: {
          groupId: group.id,
          userId: user.id,
          memberType: "user",
          role: "creator",
        },
      });

      // 3. Thêm các thành viên khác
      if (memberIds && Array.isArray(memberIds)) {
        for (const mId of memberIds) {
          const parsedId = parseInt(mId);
          if (!isNaN(parsedId) && parsedId !== user.id) {
            // Kiểm tra xem user được add có tồn tại không
            const memberExists = await tx.user.findUnique({
              where: { id: parsedId },
            });
            if (memberExists) {
              await tx.chatGroupMember.create({
                data: {
                  groupId: group.id,
                  userId: parsedId,
                  memberType: "user",
                  role: "member",
                },
              });
              
              // Tạo notification cho thành viên mới
              await tx.notification.create({
                data: {
                  userId: parsedId,
                  senderId: user.id,
                  type: "group_invite",
                  targetId: group.id,
                }
              });
            }
          }
        }
      }

      return group;
    });

    return NextResponse.json({ message: "Tạo nhóm chat thành công!", group: newGroup }, { status: 201 });
  } catch (error) {
    console.error("Create Chat Group Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
