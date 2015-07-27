<?php 
class WC_MahaCOD_Debug {
    var $handle = null;
    public function __construct(){}
    private function open() {
		if ( isset( $this->handle ) ){return true;}
		if ( $this->handle = @fopen( untrailingslashit( plugin_dir_path( __FILE__ ) ).'/log/maha_log.txt', 'a' ) ){return true;}
		return false;
	}
    public function write($text) {
        return ;
        if ( $this->open() && is_resource( $this->handle) ) {
			$time = date_i18n( 'm-d-Y @ H:i:s -' ); //Grab Time
			@fwrite( $this->handle, $time . " " . $text . "\n" );
		}
		@fclose($this->handle);
    }
    public function sep(){
        $this->write('------------------------------------'."\n");
    }
}
?>