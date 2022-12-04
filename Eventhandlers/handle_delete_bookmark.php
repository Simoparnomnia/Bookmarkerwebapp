<?php

//TODO: incomplete

if(isset($_POST["delete_bookmark"])){
    
    
    try{

        require dirname((__DIR__)).'/vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable((dirname(__DIR__)));
        $DOTENVDATA=$dotenv->load();

        require_once('./connect_database.php');
        $bookmark_from_post=substr_replace($_POST["delete_bookmark"],"",0,strlen("DELETE BOOKMARK "));

        $check_if_bookmark_exists_only_for_the_user_query=$connection->prepare("SELECT COUNT(username), username, tags_id FROM bookmarksofusers WHERE url=?");
        $check_if_bookmark_exists_only_for_the_user_query->bind_param("s",$bookmark_from_post);
        
        if($check_if_bookmark_exists_only_for_the_user_query->execute()){
            echo "SUCCEEDED CHECKING FOR BOOKMARK COUNT, BOOKMARK FROM POST".$bookmark_from_post;
            
            $check_if_bookmark_exists_only_for_the_user_query->store_result();
            $check_if_bookmark_exists_only_for_the_user_query->bind_result($bookmark_for_the_user_count, $tagged_bookmarks_username, $tags_id);
            while($check_if_bookmark_exists_only_for_the_user_query->fetch()){
                //If the bookmark exists only for the user, it is safe and faster to delete the actual bookmark (and possibly the relevant tags) instead of the reference as weöö
                //Ignore other users
                if($bookmark_for_the_user_count==1 && $tagged_bookmarks_username==$_SESSION["username"]){
                    echo "SUCCEEDED CHECKING IF USER COUNT IS 1";
                    
                    //first check if the bookmark's tags exist only for the current user
                    $check_if_tags_exists_only_for_the_users_bookmark_query=$connection->prepare("SELECT COUNT(username) FROM bookmarksofusers WHERE url=? AND tags_id=(SELECT tags_id FROM tagsofbookmarks WHERE tags_id=?)");
                    $check_if_tags_exists_only_for_the_users_bookmark_query->bind_param("si",$bookmark_from_post,$tags_id);
                    
                    if($check_if_tags_exists_only_for_the_users_bookmark_query->execute()){
                        echo "SUCCEEDED CHECKING FOR USER TAG COUNT ";
                        
                        $check_if_tags_exists_only_for_the_users_bookmark_query->store_result();
                        $check_if_tags_exists_only_for_the_users_bookmark_query->bind_result($tags_count_for_users_bookmark);
                        if($tags_count_for_users_bookmark==1){
                            //it is safe to delete the tags aswell
                            $delete_tags_query=$connection->prepare("DELETE FROM tagsofbookmarks WHERE tags_id=?");
                            $delete_tags_query->bind_param("i",$tags_id);
                            if($delete_tags_query->execute()){
                                
                                $delete_bookmark_query=$connection->prepare("DELETE FROM bookmark WHERE url=?");
                                $delete_bookmark_query->bind_param("s",$bookmark_from_post);
                                if($delete_bookmark_query->execute()){

                                    header('Location: ../index.php?page=bookmarks_page&bookmark_deleted_status=yes');
                
                                }
                                else{
                                    echo "BOOKMARK COUNT 1, FAILED TO DELETE THE BOOKMARK";
                                    exit();
                                }
                            }
                            else{
                                echo "BOOKMARK COUNT 1, FAILED TO DELETE TAGS FOR THE CURRENT USER";
                                exit();
                            }
                        }
                        else{
                            //otherwise only delete the bookmark
                            $delete_bookmark_query=$connection->prepare("DELETE FROM bookmark WHERE url=?");
                            $delete_bookmark_query->bind_param("s", $bookmark_from_post);
                            if($delete_bookmark_query->execute()){
                                
                                header('Location: ../index.php?page=bookmarks_page&bookmark_deleted_status=yes');
                                
                            }
                        }
                        $check_if_tags_exists_only_for_the_users_bookmark_query->free_result();
                    }
                    else{
                        echo "BOOKMARK COUNT 1, FAILED TO CHECK TAGS FOR THE CURRENT USER";
                        exit();
                    }
                }


                //if the bookmark exists for other users, delete only the reference and the possible relevant tags, ignore other users
                elseif($bookmark_for_the_user_count>1 && $tagged_bookmarks_username==$_SESSION["username"])
                {
                    echo "SUCCEEDED CHECKING FOR USER COUNT 2 OR MORE";
                    
                    //First check the tags 
                    $check_if_tags_exists_only_for_the_users_bookmark_query=$connection->prepare("SELECT COUNT(username) FROM bookmarksofusers WHERE url=? AND tags_id=(SELECT tags_id FROM tagsofbookmarks WHERE tags_id=?)");
                    $check_if_tags_exists_only_for_the_users_bookmark_query->bind_param("si",$bookmark_from_post,$tags_id);
                    if($check_if_tags_exists_only_for_the_users_bookmark_query->execute()){
                        echo "SUCCEEDED CHECKING USER BOOKMARK TAGS COUNT";
                        exit();
                        $check_if_tags_exists_only_for_the_users_bookmark_query->store_result();
                        $check_if_tags_exists_only_for_the_users_bookmark_query->bind_result($tags_count_for_users_bookmark);
                        if($tags_count_for_users_bookmark==1){
                            echo "SUCCEEDED BOOKMARK USER COUNT 2 OR MORE AND TAGS COUNT 1";
                            
                            //It is safe to delete the tags aswell
                            $delete_user_bookmark_query=$connection->prepare("DELETE FROM tagsofbookmarks, bookmark, bookmarksofusers WHERE AND url=? AND bookmarksofusers.username=?)");
                            $delete_user_bookmark_query->bind_param("i", $tags_count_for_users_bookmark);
                            if($delete_user_bookmark_query->execute()){
                                header('Location: ../index.php?page=bookmarks_page&bookmark_deleted_status=yes');
                            }
                            else{
                                echo "BOOKMARK COUNT 2 OR MORE, TAGS COUNT 1, FAILED TO DELETE BOOKMARK FOR THE CURRENT USER";
                                exit();
                            }
                        }
                        else{
                            echo "SUCCEEDED CHECKING BOOKMARK 2 ORE MORE, TAGS COUNT 2 OR MORE";
                            
                            //Otherwise only delete the bookmark
                            $delete_user_bookmark_query=$connection->prepare("DELETE FROM tagsofbookmarks, bookmark, bookmarksofusers WHERE AND url=? AND bookmarksofusers.username=?)");
                            $delete_user_bookmark_query->bind_param("i", $tags_count_for_users_bookmark);
                            if($delete_user_bookmark_query->execute()){
                                echo "SUCCEEDED DELETING BOOKMARK WHEN BOOKMARKS 2 OR MORE, TAGS COUNT 2 OR MORE";
                                exit();
                                header('Location: ../index.php?page=bookmarks_page&bookmark_deleted_status=yes');
                            }
                            else{
                                echo "BOOKMARK COUNT 2 OR MORE, TAGS COUNT 2 OR MORE, FAILED TO DELETE BOOKMARK FOR THE CURRENT USER";
                                exit();
                            }
                        }
                    }
                    else{
                        echo "BOOKMARK COUNT 2 OR MORE, FAILED TO CHECK TAGS FOR THE CURRENT USER";
                        exit();
                    }
                }
                
            }
            $check_if_bookmark_exists_only_for_the_user_query->free_result();
        
        }
    }catch(Exception $e){
        //database error
        echo $e;
        exit();
        header('Location: ../index.php?page=bookmarks_page&bookmark_deleted_status=no');
        
    }
}





?>