<?php

namespace App\Http\Controllers;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\Session;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Session_levels;

class RegisterController extends Controller
{
    private $session_id,$service_code,$phone_number,$text;
    private $AT_username, $AT_api_key;
    private AfricasTalking $AT;

    protected string $screen_response,$header;
    protected $text_array,$user_response;
    protected int $level;

    public function __construct(Request $request){

        $this->session_id   =$request->get('sessionId');
        $this->service_code =$request->get('serviceCode');
        $this->phone_number =$request->get('phoneNumber');
        $this->text         =$request->get('text');

        $this->AT_api_key   =env('AT_API_KEY');
        $this->AT_username  =env('AT_USERNAME');
        $this->AT           =new AfricasTalking($this->AT_username,$this->AT_api_key);

        $this->header           ="Content-type:text/plain";
        $this->screen_response  ="";
        $this->level            =0;
        $this->text_array       =explode("*",$this->text);

        $this->user_response    =trim(end($this->text_array));
    }

    public function register_user() {
        $new_level=(new Session_levels())->where('phone_number',$this->phone_number)->pluck('session_level')->first();
        if(!empty($new_level)){
            $this->level=$new_level;
        }
        $user=(new User())->whereNotNull('email')->count();
       // dd($user);
        if($user>0){
            switch ($this->level){
                case 0:
                    switch ($this->user_response){
                        case "":
                            (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>1]);
                            $this->display_main_menu();
                            break;
                    }
                    break;
                case 1:
                    switch ($this->user_response){
                        case "1":
                            $this->send_sms();
                            break;
                        case "2":
                            $this->voice_call();
                            break;
                        case "3":
                            $this->send_airtime();
                            break;
                    }
                    break;
            }
        }
        else{
            switch ($this->level){
                case 0:
                    switch ($this->user_response){
                        case "":
                            Session_levels::create([
                                'session_id'    =>$this->session_id,
                                'session_level' =>1,
                                'phone_number'  =>$this->phone_number
                            ]);
                            User::create([
                                'phone_number'  =>$this->phone_number
                            ]);

                            $this->display_registration_form();
                            break;
                    }
                    break;
                case 1:
                    switch ($this->user_response){
                        case "1":
                            $this->user_firstname();
                            (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>2]);
                            break;
                        case "0":
                            (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>0]);
                            break;
                    }
                    break;
                case 2:

                    $first_name=$this->user_response;
                    //dd($first_name);
                    (new User())->where("phone_number",$this->phone_number)->update(["first_name"=>$first_name]);
                    $this->user_lastname();
                    (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>3]);
                    break;
                case 3:
                    (new User())->where("phone_number",$this->phone_number)->update(["last_name"=>$this->user_response]);
                    $this->username();
                    (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>4]);
                    break;
                case 4:
                    (new User())->where("phone_number",$this->phone_number)->update(["username"=>$this->user_response]);
                    $this->user_email();
                    (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>5]);
                    break;
                case 5:
                    (new User())->where("phone_number",$this->phone_number)->update(["email"=>$this->user_response]);
                    //take user to main menu
                    (new Session_levels())->where('phone_number',$this->phone_number)->update(['session_level'=>0]);
                    $this->display_main_menu();
                    break;
            }
        }
    }
    public function display_main_menu()
    {
        $this->screen_response="<strong>Welcome to Safaricom Services</strong>\n";
        $this->screen_response.="1.Send me today's football fixtures\n";
        $this->screen_response.="2.Please call me!\n";
        $this->screen_response.="3.Send me airtime";
        $this->header;
        $this->ussd_proceed($this->screen_response);
    }
    public function display_registration_form()
    {
        $this->screen_response="<strong>Welcome to Safaricom Classes</strong>\n";
        $this->screen_response.="1.Register to proceed\n";
        $this->screen_response.="0.Back\n";
        $this->header;
        $this->ussd_proceed($this->screen_response);
    }

    public function send_sms()
    {
        $message = "1.Man Utd Vs Crystal Palace\n 2.Chelsea vs Liverpool";
        $short_code = 18954;
        $recipients = $this->phone_number;

        $sms = $this->AT->sms();
        $sms->send([
            "to" => $recipients,
            "from" => $short_code,
            "message" => $message
        ]);

        $this->screen_response = "Please check your SMS inbox\n";
        $this->header;
        $this->ussd_finish($this->screen_response);
    }
       public function send_airtime()
    {
        $parameter=[
            "recipients"=>[[
                "phoneNumber"   =>$this->phone_number,
                "currencyCode"  =>"KES",
                "amount"        =>"1000"
            ]]
        ];
        $airtime=$this->AT->airtime();
        $airtime->send($parameter);

        $this->screen_response="Please wait as we load your $this->phone_number account";
        $this->header;
        $this->ussd_finish($this->screen_response);
    }

    public function voice_call()
    {
        $from   ="+254717720862";
        $to     =$this->phone_number;

        $this->AT->voice($from,$to);
        $this->screen_response="Please wait as we place your call on the line";
        $this->header;
        $this->ussd_finish($this->screen_response);
    }

    public function ussd_proceed($proceed)
    {
        echo "CON $proceed";
    }
    public function ussd_finish($stop)
    {
        echo "END $stop";
    }
    public function user_firstname()
    {
        $this->screen_response="Enter your first name\n";
        $this->ussd_proceed($this->screen_response);
    }
    public function user_lastname()
    {
        $this->screen_response="Enter your last name\n";
        $this->ussd_proceed($this->screen_response);
    }
    public function username()
    {
        $this->screen_response="Enter your username\n";
        $this->ussd_proceed($this->screen_response);
    }
    public function user_email()
    {
        $this->screen_response="Enter your email address\n";
        $this->ussd_proceed($this->screen_response);
    }
}
