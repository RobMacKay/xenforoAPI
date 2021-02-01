# xenforoAPI
Xenforo API 2.2 Login and User Area.

Things to note! 

1. Same Domain cookie.  This works within the same domain as the forums, and relies on cooking information set by xenforo. If you are not able to login after completing all the  details it's because you are not on the same domain. 
2. No POST method examples defined yet. I've not got there. 
3. The CURL method accepts 3 arguments.  You should be able to build most queries with this - no support currently for file uploads yet though. 
    a. Query - An array of Parameters
    b. Method - Defaults to GET but can be POST.
    c. Endpoint - this specifies what endpoint you would like to interact with. 
    
# The Flow

Class is initialised and construct sets some private vars and uses *\_handlelogin* to check if any POST has been sent. 
It also using *\_getSession* to check if there is a session and if a user is logged in already and gets those details. 

Depending on the status of the session the user will be given either an option to login or display a basic profile icon from FontAwesome and any unread notifications.  This will linked to the users account. 
