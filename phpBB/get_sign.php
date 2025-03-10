<?php
   ini_set('display_errors',"Off");
   //session_start() ; 
  class get_sign {
      private $dbuser = 'DB_USER' ;
      private $pass = 'DB_PASS' ; 
      protected $db ;         
				protected $sth ; 

      public function __construct(){
           
					$dsn="DB_CONNECTION"  ;		          
					$query="select user_sig from phpbb_users where username=:uid";
					$this->db=new PDO( $dsn , $this->dbuser,$this->pass ) ; 
					$this->sth=$this->db->prepare( $query ) ; 	
        }
				
				public function get_data( $filter ){
					$this->sth->execute( array( ':uid'=>trim($filter) ) ) ; 
					$res=$this->sth->fetchAll() ; 
					if ( array_key_exists( 0 , $res ) and ( strlen( $res[0]['user_sig'])>0  ) ) {
						$ua=$res[0]['user_sig'] ; 
					} else {
						$ua=$filter ; 
					}  

					return $ua; 
				}

    }


    if (false) {
				$uid = $_REQUEST['user_id'] ; 
     	$obj = new get_sign() ; 
				print( $obj->get_data( $uid ) ) ; 

    }


?>
