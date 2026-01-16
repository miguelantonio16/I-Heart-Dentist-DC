<?php

    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
            header("location: login.php");
        }

    }else{
        header("location: login.php");
    }
    
    
    if($_GET){
        //import database
        include("../connection.php");
        $id=$_GET["id"];
  
        $sql= $database->query("delete from appointment where appoid='$id';");
        require_once __DIR__ . '/../inc/redirect_helper.php';
        // preserve optional return parameters from GET
        $returnParams = [];
        if (isset($_GET['page'])) { $returnParams['page'] = (int)$_GET['page']; }
        if (isset($_GET['search']) && $_GET['search'] !== '') { $returnParams['search'] = $_GET['search']; }
        if (isset($_GET['sort']) && $_GET['sort'] !== '') { $returnParams['sort'] = $_GET['sort']; }
        if (!empty($returnParams)) {
            redirect_with_context('my_appointment.php', $returnParams);
        } else {
            redirect_with_context('my_appointment.php');
        }
    }


?>