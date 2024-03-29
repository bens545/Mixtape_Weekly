<!doctype html>
<html>
<head>
    <title>Mixtape</title>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <script src="spotify.min.js"></script>
    <!-- <script src="underscore.js"></script> -->
    <style>
        div.song{
            border: 2px solid black;
            width: 500px;
        }

        div.time{
            float:left;
        }
    </style>
</head>
<body>
<script>
    var client_id = '<?php echo getenv("client_id") ?>';
    var redirect_uri = '<?php echo getenv("redirect_uri") ?>';
    var username;
    var accessToken;
    var s = new SpotifyWebApi();
    //var h = 1;
    var songs = [];
    var artists = [];
    var g_tracks = [];
    $(document).ready(function() {
        var my_songs = [];
        var lowerCaseSongs = [];
        var my_artists = [];
        var my_uris = [];

        function fetchSavedTracks(callback) {
            var url = 'https://api.spotify.com/v1/me/tracks?limit=50';
            callSpotify(url, {}, callback);
        }
        function callSpotify(url, data, callback) {
            $.ajax(url, {
                dataType: 'json',
                data: data,
                headers: {
                    'Authorization': 'Bearer ' + accessToken
                },
                success: function(r) {
                    callback(r);
                },
                error: function(r) {
                    callback(null);
                }
            });
        }
        function showTracks(tracks) {
            console.log('show tracks', tracks);
            $.each(tracks.items, function(i, item) {
                my_songs.push(item.track.name);
                my_artists.push(item.track.artists[0].name);
                my_uris.push(item.track.uri);
            });
            if (tracks.next) {
                callSpotify(tracks.next, {}, function(tracks) {
                    showTracks(tracks);
                });
            }else{
                //console.log(my_songs, my_artists, my_uris);
                lowerCaseSongs = my_songs.map(function(value){
                    return value.toLowerCase();
                });
                $.each(songs, function(i, song){
                    searchForSong(song, artists[i], i);
                });
            }
        }

        function getTopList(place, url, h){
            $.getJSON(url, function(json) {
                var html = '';
                $.each(json.toptracks.track, function(i, item) {
                    songs[h] = item.name;
                    artists[h] = item.artist.name;
                    html += "<div id='"+h+"' class='song'><p> " + (h+1) + "<image src='" +item.image[1]['#text'] + "'/><a href=" + item.url + " target='_blank'>" + item.name + " - Artist: " + item.artist.name + "</br>Play count: " +item.playcount + "</a></p></div>";
                    h++
                });
                $('#' + place).append(html);
            });
        }

        var doLogin = function(callback) {
            var url = 'https://accounts.spotify.com/authorize?client_id=' + client_id +
                '&response_type=token' +
                '&scope=user-library-read%20playlist-read-private%20playlist-read-collaborative%20playlist-modify-public%20playlist-modify-private' +
                '&redirect_uri=' + encodeURIComponent(redirect_uri);
            //document.location = url;
            //localStorage.setItem('createplaylist-tracks', JSON.stringify(g_tracks));
            var w = window.open(url, 'asdf', 'WIDTH=400,HEIGHT=500');
        }

        //doLogin(function() {});
        getTopList("year", "http://ws.audioscrobbler.com/2.0/?method=user.getTopTracks&user=ben545&period=1year&api_key=d1baeba3d3799d63d87029d887b936d1&limit=5&format=json&callback=?", 0);
        getTopList("month", "http://ws.audioscrobbler.com/2.0/?method=user.getTopTracks&user=ben545&period=1month&api_key=d1baeba3d3799d63d87029d887b936d1&limit=5&format=json&callback=?", 5);
        getTopList("week", "http://ws.audioscrobbler.com/2.0/?method=user.getTopTracks&user=ben545&period=7day&api_key=d1baeba3d3799d63d87029d887b936d1&limit=20&format=json&callback=?", 10)


        var doSearch = function(word, artist, callback) {
            console.log('search for ' + word);
            var url = 'https://api.spotify.com/v1/search?type=track&limit=5&market=us&q=' + encodeURIComponent('track:"'+word+'" artist:"'+artist+'"');
            $.ajax(url, {
                dataType: 'json',
                success: function(r) {
                    //console.log(r);
                    callback({
                        word: word,
                        tracks: r.tracks.items
                            .map(function(item) {
                                var ret = {
                                    name: item.name,
                                    artist: 'Unknown',
                                    album: item.album.name,
                                    uri: item.uri
                                }
                                if (item.artists.length > 0) {
                                    ret.artist = item.artists[0].name;
                                }
                                return ret;
                            })
                    });
                },
                error: function(r) {
                    callback({
                        word: word,
                        tracks: []
                    });
                }
            });
        }

        function searchMySongs(song, artist){
            var index = lowerCaseSongs.indexOf(song.toLowerCase());
            //console.log(index);
            if((index > 0) && (artist.toLowerCase() == my_artists[index].toLowerCase())){
                //console.log(my_songs[index], my_artists[index]);
                return index;
            }else{
                return -1;
            }
        }

        function searchForSong(song, artist, i){
            var mine = searchMySongs(song, artist);
            //console.log(mine);
            if(mine == -1){
                doSearch(song, artist, function(result) {
                    g_tracks.push(result.tracks[0].uri);
                    $('#'+i).append('<p>' + result.tracks[0].name +" - "+ result.tracks[0].artist +"</p>");
                    $('#'+i).append('<p>This song is not in your library!</p>');
                });
            }else{
                $('#'+i).append('<p>' + my_songs[mine] +' - '+ my_artists[mine] +'</p>');
                g_tracks.push(my_uris[mine]);
            }
        }

        $('#playlist').click(function(){
            //console.log(g_tracks);
            var playlist = '<?php echo getenv("playlist_id") ?>';
            s.replaceTracksInPlaylist(username, playlist, g_tracks);
            $(this).prop("disabled", true);
            //getMySongs();
            //isListed();
        })

        var isListed = function(){
            var song_id = "";
            $.each(g_tracks, function(i, song){
                song_id = song_id + song.substring(14) + ",";
            })
            song_id = song_id.slice(0, -1);
            console.log(song_id);
            //console.log(s.containsMySavedTracks(song_id));

            var url = 'https://api.spotify.com/v1/me/tracks/contains?ids='+ encodeURIComponent(song_id);
            $.ajax(url, {
                method: 'GET',
                //data: JSON.stringify(song_id),
                dataType: 'text',
                headers: {
                    'Authorization': 'Bearer ' + accessToken,
                    'Content-Type': 'application/json'
                },
                success: function(r) {
                    r = JSON.parse(r);
                    console.log('It exists!', r);
                    //console.log(s.getMySavedTracks({limit: 50, offset:500}));
                    //callback(r.id);
                    $.each(r, function(i, b){
                        if(!b){
                            $('#'+i).append('<p>This song is not in your songs.</p>');
                        }else{
                            $('#'+i).append('<p><br/></p>');
                        }
                    })
                },
                error: function(r) {
                    callback(null);
                }
            });

        }

        function authorizeUser() {
            var url = 'https://accounts.spotify.com/authorize?client_id=' + client_id +
                '&response_type=token' +
                '&scope=user-library-read%20playlist-read-private%20playlist-read-collaborative%20playlist-modify-public%20playlist-modify-private' +
                '&redirect_uri=' + encodeURIComponent(redirect_uri);
            document.location = url;
        }


        function parseArgs() {
            var hash = location.hash.replace(/#/g, '');
            var all = hash.split('&');
            var args = {};
            $.each(all, function(index, keyvalue) {
                var kv = keyvalue.split('=');
                var key = kv[0];
                var val = kv[1];
                args[key] = val;
            });
            return args;
        }

        var args = parseArgs();
        if ('access_token' in args) {
            accessToken = args['access_token'];
            s.setAccessToken(accessToken)
            s.getMe(function(error, user) {
                if(error){
                    authorizeUser();
                }
                if (user) {
                    username = user.id;
                    fetchSavedTracks(function(data) {
                        if (data) {
                            showTracks(data);
                        }
                    });
                }
            });
        } else {
            authorizeUser();
        }

    });
</script>
<div id="year" class="time">
    <h2>Last Year</h2>
</div>
<div id="month" class="time">
    <h2>Last Month</h2>
</div>
<div id="week" class="time">
    <h2>Last Week</h2>
</div>
<!-- <button id="login">Login!</button> -->
<button id="playlist">Add to Playlist</button>
</body>
</html>