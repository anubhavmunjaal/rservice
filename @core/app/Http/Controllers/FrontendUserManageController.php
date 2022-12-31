<?php

namespace App\Http\Controllers;

use App\Country;
use App\ServiceArea;
use App\ServiceCity;
use App\User;
use App\Helpers\FlashMsg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\BasicMail;
use App\Mail\SingleMailToUser;
use App\Accountdeactive;
use App\SellerVerify;
use App\Service;


class FrontendUserManageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:user-list|user-create|user-edit|user-delete',['only' => 'all_user']);
        $this->middleware('permission:user-delete',['only' => ['bulk_action','new_user_delete']]);
    }

    public function all_user()
    {
        $all_user = User::with('sellerVerify')->get();
        $countries = Country::all();
        $cities = ServiceCity::all();
        $areas = ServiceArea::all();
        return view('backend.frontend-user.all-user',compact('all_user','countries','cities','areas'));
    }

    public function sellerAll()
    {
        $all_user = User::with('sellerVerify')
            ->where('user_type',0)
            ->get();
        return view('backend.pages.seller-verify.verify')->with(['all_user' => $all_user]);
    }

    public function userStatus($id=null)
    {   
        $user_status = User::select('user_status')->find($id);
        User::where('id', $id)->update([
            'user_status' => $user_status->user_status== 0 ? 1 : 0
        ]);

        $user_status_2 = User::select('user_status')->find($id);
        if($user_status_2->user_status == 0){
            Service::where('seller_id',$id)
            ->update(['status'=>0]);
        }
        if($user_status_2->user_status == 1){
            Service::where('seller_id',$id)
            ->update(['status'=>1]);
        }
        return redirect()->back()->with(FlashMsg::item_new('Status change success--'));
    }

   //seller profile view
    public function sellerProfileView($id=null){
        $seller_details = User::with('sellerVerify')->where('id',$id)->first();
        return view('backend.frontend-user.seller-details',compact('seller_details'));
    }

    //seller verify
    public function sellerVerify($id)
    {   
        $seller_status = SellerVerify::select('status')->where('seller_id',$id)->firstOrCreate([
            'seller_id' => $id
        ]);
       $verify_seller = SellerVerify::where('seller_id', $id)->update([
            'status' => $seller_status->status === 0 ? 1 : 0
        ]);

       if($verify_seller){
           $seller = User::select('id','email','name')->where('id',$id)->first();
           try {
               $message = get_static_option('admin_seller_verification_message') ?? '';
               $message = str_replace(["@name"],[$seller->name],$message);
               Mail::to($seller ->email)->send(new BasicMail([
                   'subject' => get_static_option('admin_seller_verification_subject') ?? __('Seller Verification Success'),
                   'message' => $message
               ]));
           } catch (\Exception $e) {
               return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
           }
       }
        return redirect()->back()->with(FlashMsg::item_new('Status change success--'));
    }

    public function userDelete($id=null)
    {
        User::find($id)->delete();
        return redirect()->back()->with(FlashMsg::item_new('User delete success--'));
    }

    public function bulk_action(Request $request)
    {
        $all = User::find($request->ids);
        foreach ($all as $item) {
            $item->delete();
        }
        return response()->json(['status' => 'ok']);
    }

    public function email_verify_code($id=null){
       $user_details =  User::find($id);
       try {
            $message = get_static_option('admin_user_verification_code_message') ?? '';
            $message = str_replace(["@name","@verification_code"],[$user_details->name,$user_details->email_verify_token],$message);
            Mail::to($user_details->email)->send(new BasicMail([
                'subject' => get_static_option('admin_user_verification_code_subject') ?? __('Verification Code Send Success'),
                'message' => $message
            ]));
        }catch (\Exception $e){
             return redirect()->back()->with(FlashMsg::item_new( $e->getMessage()));
        }
       return redirect()->back()->with(FlashMsg::item_new(__('Verification Code Send Success')));
    }

    public function send_mail_to_single_user(Request $request){

        $this->validate($request,[
           'email' => 'required|email',
           'subject' => 'required',
           'message' => 'required',
        ]);

        $data = [
          'email' => $request->email,
          'subject' => $request->subject,
          'message' => $request->message,
        ];

        try {
            Mail::to($request->email)->send(new SingleMailToUser($data));
        }catch (\Exception $ex){
            return redirect()->back()->with(FlashMsg::item_delete($ex->getMessage()));
        }

        return redirect()->back()->with([
            'msg' => __('Mail Send Success...'),
            'type' => 'success'
        ]);
    }
    
     public function deactive_user()
    {
        $all_user = Accountdeactive::all();
        return view('backend.frontend-user.all-deactive-user')->with(['all_user' => $all_user]);
    }

    public function getCity(Request $request)
    {
        $cities = ServiceCity::where('country_id', $request->country_id)->where('status', 1)->get();
        return response()->json([
            'status' => 'success',
            'cities' => $cities,
        ]);
    }

    public function getAarea(Request $request)
    {
        $areas = ServiceArea::where('service_city_id', $request->city_id)->where('status', 1)->get();
        return response()->json([
            'status' => 'success',
            'areas' => $areas,
        ]);
    }

    public function updateUserInfo (Request $request)
    {
        $old_image = User::select('image')->where('email',$request->edit_email)->first();
        User::where('email', $request->edit_email)
            ->update([
                'name' => $request->edit_name,
                'phone' => $request->edit_phone,
                'country_id' => $request->edit_country,
                'service_city' => $request->edit_city,
                'service_area' => $request->edit_area,
                'address' => $request->edit_address,
                'image' => $request->edit_image ?? $old_image->image,
            ]);
        return redirect()->back()->with(FlashMsg::item_new(__('User info update success')));
    }

    public function changeUserPassword(Request $request){
        $request->validate([
            'user_new_password_email' => 'required|email',
            'user_new_password' => 'required',
        ]);

        User::where('email',$request->user_new_password_email)->update(['password' => Hash::make($request->user_new_password)]);
        try {
            $message = get_static_option('admin_user_new_password_message') ?? '';
            $message = str_replace(["@new_password"],[$request->user_new_password],$message);
            Mail::to($request ->user_new_password_email)->send(new BasicMail([
                'subject' => get_static_option('admin_user_new_password_subject') ?? __('Password Change Success'),
                'message' => $message
            ]));
        } catch (\Exception $e) {
            return redirect()->back()->with(FlashMsg::item_new($e->getMessage()));
        }

        return redirect()->back()->with([
            'msg' => __('Mail Send Success...'),
            'type' => 'success'
        ]);
    }

}
