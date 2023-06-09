<?php
namespace Core\Vk_profiles\Controllers;

class Vk_profiles extends \CodeIgniter\Controller
{
    public function __construct(){
        $reflect = new \ReflectionClass(get_called_class());
        $this->module = strtolower( $reflect->getShortName() );
        $this->config = include realpath( __DIR__."/../Config.php" );
        include get_module_dir( __DIR__ , 'Libraries/vkapi.php');

        $this->app_id = get_option('vk_app_id', '');
        $this->secure_secret = get_option('vk_secure_secret', '');

        if($this->app_id == "" || $this->secure_secret == ""){
            redirect_to( base_url("social_network_settings/index/".$this->config['parent']['id']) ); 
        }

        $this->vk = new \Vkapi($this->app_id, $this->secure_secret); 
    }
    
    public function index() {

        try {
            if(!get_session("Vk_AccessToken")){
                redirect_to( get_module_url("oauth") );
            }

            $accessToken = get_session("Vk_AccessToken");
            $this->vk->set_access_token($accessToken);

            $response = $this->vk->get_user_info()[0];

            $result = [];
            $result[] = (object)[
                'id' => $response->id,
                'name' => $response->first_name." ".$response->last_name,
                'avatar' => $response->photo_big,
                'desc' => $response->screen_name
            ];

            $profiles = [
                "status" => "success",
                "config" => $this->config,
                "result" => $result
            ];
        } catch (\Exception $e) {
            $profiles = [
                "status" => "error",
                "config" => $this->config,
                "message" => $e->getMessage()
            ];
        }

        $data = [
            "title" => $this->config['name'],
            "desc" => $this->config['desc'],
            "content" => view('Core\Vk_profiles\Views\add', $profiles)
        ];

        return view('Core\Vk_profiles\Views\index', $data);
    }

    public function oauth(){
        $oauth_link = $this->vk->login_url();

        $data = [
            "title" => $this->config['name'],
            "desc" => $this->config['desc'],
            "content" => view('Core\Vk_profiles\Views\oauth', [ "config" => $this->config, "oauth_link" => $oauth_link ])
        ];

        return view('Core\Vk_profiles\Views\index', $data);
    }

    public function token()
    {
        try {
            $code = post("code");
            $code_arr = explode("=", $code);
            if(count($code_arr) == 2){
                $code = $code_arr[1];
            }

            if(empty($code)){
                ms([
                    "status" => "error",
                    "message" => __('Please enter access token')
                ]);
            }

            $response = $this->vk->get_access_token($code);

            if(isset($response['error'])){
                ms($response);
            }

            set_session(["Vk_AccessToken" => $response]);

            ms([
                "status" => "success",
                "message" => __("Success")
            ]);
        } catch (\Exception $e) {
            ms([
                "status" => "error",
                "message" => __( $e->getMessage() )
            ]);
        }
    }

    public function save()
    {
        try {
            $ids = post('id');
            $team_id = get_team("id");

            validate('empty', __('Please select a profile to add'), $ids);

            $accessToken = get_session("Vk_AccessToken");
            $this->vk->set_access_token($accessToken);

            $response = $this->vk->get_user_info()[0];

            if(!is_string($response)){

                if( in_array($response->id, $ids) ){
                    $item = db_get('*', TB_ACCOUNTS, "social_network = 'vk' AND team_id = '{$team_id}' AND pid = '".$response->id."'");

                    if(!$item){
                        //Check limit number 
                        check_number_account("vk", "profile");
                        $avatar = save_img( $response->photo_big, WRITEPATH.'avatar/' );
                        $data = [
                            'ids' => ids(),
                            'module' => $this->module,
                            'social_network' => 'vk',
                            'category' => 'profile',
                            'login_type' => 1,
                            'can_post' => 1,
                            'team_id' => $team_id,
                            'pid' => $response->id,
                            'name' => $response->first_name." ".$response->last_name,
                            'username' => $response->screen_name,
                            'token' => $accessToken,
                            'avatar' => $avatar,
                            'url' => 'https://vk.com/'.$response->screen_name,
                            'data' => NULL,
                            'status' => 1,
                            'changed' => time(),
                            'created' => time()
                        ];

                        db_insert(TB_ACCOUNTS, $data);
                    }else{
                        unlink( get_file_path($item->avatar) );
                        $avatar = save_img( $response->photo_big, WRITEPATH.'avatar/' );
                        $data = [
                            'can_post' => 1,
                            'pid' => $response->id,
                            'name' => $response->first_name." ".$response->last_name,
                            'username' => $response->screen_name,
                            'token' => $accessToken,
                            'avatar' => $avatar,
                            'url' => 'https://vk.com/'.$response->screen_name,
                            'status' => 1,
                            'changed' => time(),
                        ];

                        db_update(TB_ACCOUNTS, $data, ['id' => $item->id]);
                    }
                }

                ms([
                    "status" => "success",
                    "message" => __("Success")
                ]);
            }else{
                ms([
                    "status" => "error",
                    "message" => $response
                ]);
            }

            
        } catch (\Exception $e) {
            ms([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }
}