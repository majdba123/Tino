<!DOCTYPE html>

<head>
    <title>Pusher Test</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        // Enable pusher logging - don't include this in production
        Pusher.logToConsole = true;

        var pusher = new Pusher('10d216ea57c8cc5c5030', {
            cluster: 'eu'
        });

        var channel = pusher.subscribe('notification-public-channel');
        channel.bind('NotificatinEvent', function(data) {
            alert(JSON.stringify(data));
        });
    </script>

</head>

<body>
    <h1>Notification Test</h1>
    <p>
        Try Notification an event to channel <code>my-channel</code>
        with event name <code>my-event</code>.
    </p>
</body>
