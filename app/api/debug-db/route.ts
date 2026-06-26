import { NextResponse } from "next/server";
import { db } from "@/lib/db";

export async function GET() {
  try {
    const result = await db.$queryRaw`SELECT NOW() as time, current_database() as db, current_user as user`;
    return NextResponse.json({ 
      status: "OK", 
      connection: result,
      databaseUrl: process.env.DATABASE_URL?.replace(/:([^@]+)@/, ':***@') // ẩn password
    });
  } catch (error: any) {
    return NextResponse.json({ 
      status: "ERROR",
      message: error.message,
      code: error.code,
      errorKind: error.constructor?.name,
      databaseUrl: process.env.DATABASE_URL?.replace(/:([^@]+)@/, ':***@')
    }, { status: 500 });
  }
}
