<!DOCTYPE html>

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>user Test</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        // Enable pusher logging - don't include this in production
        Pusher.logToConsole = true;
        var pusher = new Pusher('10d216ea57c8cc5c5030', {
            cluster: 'eu',
            authEndpoint: '/pusher/auth', // updated endpoint
            auth: {
                params: {
                    userId: '2' // pass the user ID to the auth endpoint
                }
            }
        });

        var channel = pusher.subscribe('chat-private-channel-2');
        channel.bind('Private_chat', function(data) {
            alert(JSON.stringify(data));
        });
    </script>
</head>



<body>
    <h1> user Test</h1>
    <h1>User ID:2</h1>
    <p>
        Try Notification an event to channel <code>user.2</code>
        with event name <code>my-event</code>.
    </p>
    <div id="notifications">
        <!-- notifications will be displayed here -->
    </div>
</body>
