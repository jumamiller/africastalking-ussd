<?php

namespace App\Http\Controllers;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Session_levels;

class RegisterController extends Controller
{
    /**
     * AT values POST request
     * @var $session_id
     * @var $service_code
     * @var $phone_number
     * @var $text
     */

    private $session_id;
    private $service_code;
    private $phone_number;
    private $text;

    /**
     * AT api credentials
     * @var $AT_username
     * @var $AT_api_key
     */

    private $AT_username;
    private $AT_api_key;
    private AfricasTalking $AT;

    protected string $screen_response;
    protected int $level;
    protected $user_response;
    protected $text_array;
    protected string $header;

    public function __construct(Request $request){
        /**
         * get the POST request values
         */
        $this->session_id   =$request->get('sessionId');
        $this->service_code =$request->get('serviceCode');
        $this->phone_number =$request->get('phoneNumber');
        $this->text         =$request->get('text');

        /**
         * initialise reusable variables
         */
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
        //current level
        $current_level =Session_levels::where("session_id",$this->session_id)
                                      ->pluck('session_level')
                                      ->first();
        if(!empty($current_level)){
            $this->level=$current_level;
        }

        //check if the user is in the database
        $user=User::where("phone_number",$this->phone_number)
                    ->get(['first_name','last_name','phone_number','email'])->first();
        $current_user=User::where("phone_number",$this->phone_number)->pluck("last_name")->first();
        //change to string
        $username=implode("",$current_user);

        if($user->count() > 0)
        {
            //user found > login user
           switch($this->level){
               case 0:
                   switch ($this->user_response){
                       case "":
                           Session_levels::create([
                               "session_id"     =>$this->session_id,
                               "session_level"  =>1,
                               "phone_number"   =>$this->phone_number
                           ]);
                           $this->displayMainMenu();
                           break;

                       default:
                           Session_levels::where("phone_number",$this->phone_number)->update(["session_level"=>0]);
                           $this->screen_response="Invalid,you have to choose a service to proceed\n";
                           $this->header;
                           $this->ussd_proceed($this->screen_response);
                   }
                   break;
               case 1:
                   switch($this->user_response){
                       case "1":
                           $this->send_sms();
                           break;
                       case "2":
                           $this->voice_call();
                           break;
                       case "3":
                           $this->send_airtime();
                           break;
                       default:
                           Session_levels::where("phone_number",$this->phone_number)->update(["session_level"=>0]);
                           $this->screen_response="Invalid,you have to choose a service to proceed\n";
                           $this->header;
                           $this->ussd_proceed($this->screen_response);
                   }
                   break;
           }
        }
        else
        {
            //user not found > register user
            switch ($this->level){
                case 0:
                    switch ($this->user_response){
                        case "":
                            Session_levels::create([
                                "session_id"    =>$this->session_id,
                                "phone_number"  =>$this->phone_number,
                                "session_level" =>1
                            ]);

                            User::create([
                                "phone_number"  =>$this->phone_number
                            ]);
                            //prompt user to enter name
                            $this->user_firstname();
                            break;
                        default:
                            
                    }

            }

        }

    }

    public function displayMainMenu()
    {

        $this->screen_response="<strong>Welcome to HLAB Online Services</strong>\n";
        $this->screen_response.="1.Send me today's football fixtures\n";
        $this->screen_response.="2.Please call me!\n";
        $this->screen_response.="3.Send me airtime";

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

        $this->AT->call($from,$to);
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
    }
    public function user_lastname()
    {
        $this->screen_response="Enter your last name\n";
    }
    public function user_email()
    {
        $this->screen_response="Enter your email address\n";
    }
}
