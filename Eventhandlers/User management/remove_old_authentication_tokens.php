<?php

if(!isset($_SESSION["käyttäjänimi"]) && !isset($_COOKIE["autentikaatiotoken"])){
    //Poistetaan vanhat tokenit tietokannasta
    $poistavanhaautentikaatiotokenkysely=$connection->prepare("DELETE FROM usertoken WHERE expiration_date < NOW()");
    try{
        $poistavanhaautentikaatiotokenkysely->execute();
            
    }catch(Exception $e){
        //database_error
        header('Location: ../../index.php?page=frontpage&database_error=kyllä');
    }
}






?>