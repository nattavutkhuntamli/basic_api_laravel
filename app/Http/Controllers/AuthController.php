<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Image;


class AuthController extends Controller
{

    public function index()
    {
        return response()->json(['message' => 'Test Api'], 201);
    }
    //
    public function register(Request $request)
    {

        $fields = Validator::make($request->all(), [
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|confirmed',
            'name' => 'required|string|max:255',
        ],[
            'username.required'=>'กรุณาระบุ username เพื่อลงทะเบียน',
            'username.unique' => 'username นี้มีอยู่ในระบบแล้ว',
            'password.required' =>'กรุณาระบุ password',
            'password.confirmed' => 'กรุณาระบุ password ให้ตรงกับ password_confirmation',
            'name.required' => 'กรุณาระบุชื่อเพื่อใช้สำหรับแสดงข้อมูล'
        ]);

        if ($fields->fails()) {
            return response()->json(['error' => $fields->errors(), 'message' => 'false'], 400);
        } else {
            //บันทึกข้อมูล
            $user = DB::table('users')->insert([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'name' => $request->name,
            ]);
            if ($user) {
                $user = User::where('username', $request->username)->first(); // หา User ที่สร้างขึ้นมา
                $token = $user->createToken('api-token')->plainTextToken; // Access Token สำหรับผู้ใช้งาน
                $body  = [
                    'user' => $user,
                    'token' => $token
                ];
                return response()->json(['message' => 'สมัครสมาชิกสำเร็จ', 'response' => $body], 201);
            } else {
                return response()->json(['message' => 'สมัครสมาชิกไม่สำเร็จ'], 400);
            }
        }
    }

    public function login(Request $request)
    {
        $fields = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ],[
            'username.required'=>'กรุณาระบุ username',
            'password.required'=>'กรุณาระบุ password'
        ]);
        if ($fields->fails()) {
            return response()->json(['error' => $fields->errors()], 400);
        } else {
            $user = User::where('username', $request->username)->first();
            if (($user->count()) != 0) {
                //ตรวจสอบ user และ ทำการเข้ารหัส passowrd
                if (!$user || !Hash::check($request->password, $user->password)) {
                    return response()->json(['message' => 'โปรดตรวจสอบ username และ password ให้ถูกต้อง'], 400);
                } else {
                    $user->tokens()->delete(); //ลบ token ตัวเก่า และสร้าง token ตัวใหม่
                    //ข้อความที่ระบุ User-Agent ที่ส่งมากับคำขอ HTTP.
                    $token = $user->createToken($request->userAgent())->plainTextToken;
                    $response = [
                        'user' => $user,
                        'token' => $token
                    ];
                    return response()->json(['message' => 'ยินดีต้อนรับเข้าสู่ระบบ', 'response' => $response], 200);
                }
            } else {
                return response()->json(['message' => 'โปรดตรวจสอบ username และ password ให้ถูกต้อง'], 400);
            }
        }
    }
    /**
     * @param \Illuminate\Http\UploadedFile $filename
     */
    public function upload_images_profile(Request $request)
    {
        $user = auth()->user();
        // รับไฟล์ภาพเข้ามา
        $image = $request->file('file');

        // เช็คว่าผู้ใช้มีการอัพโหลดภาพเข้ามาหรือไม่
        if (!empty($image)) {

            // อัพโหลดรูปภาพ
            // เปลี่ยนชื่อรูปที่ได้
            $file_name = "profile" . time() . "." . $image->getClientOriginalExtension();

            // กำหนดขนาดความกว้าง และสูง ของภาพที่ต้องการย่อขนาด
            $imgWidth = 400;
            $imgHeight = 400;
            $folderupload = public_path('/images/profile');
            $path = $folderupload . "/" . $file_name;

            // อัพโหลดเข้าสู่ folder profile
            $img =  Image::make($image->getRealPath());
            $img->orientate()->fit($imgWidth, $imgHeight, function ($constraint) {
                $constraint->upsize();
            });
            $img->save($path);

            // กำหนด path รูปเพื่อใส่ตารางในฐานข้อมูล
            $data_profile['image'] = url('/') . '/images/profile/' . $file_name;

            $UpdatProfileImage = DB::table('users')->where('id',$user->id)->update([
                'images'=>$data_profile['image']
            ]);
            if($UpdatProfileImage){
                return response()->json(['message' => 'อัพโหลดรูปภาพสำเร็จ'], 200);
            }else{
                return response()->json(['message' => 'อัพโหลดรูปภาพไม่สำเร็จ'], 404);

            }
        } else {
            return response()->json(['message' => 'กรุณาใส่รูปด้วยครับ', 'data_product' => null], 400);
        }
    }



    public function logout(Request $request)
    {
        auth()->user()->tokens()->delete();
        return response()->json(['message' => 'ออกจากระบบสำเร็จ '], 400);
    }
}
