<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
        <title>MediaWiki Feeds</title>

        <!-- Bootstrap -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">

        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body>
        <div class="container">
            <div class="page-header">
                <h1>MediaWiki Feeds</h1>
                <p>A tool to generate RSS feeds for pages in MediaWiki categories.</p>
            </div>
            <form action="feed.php" method="get" class="">
                <div class="form-group">
                    <label>Wiki URL:</label>
                    <input type="text" name="url" value="https://en.wikiversity.org/w/" size="50" class="form-control" />
                    <p class="help-block">
                        This is the URL to which index.php or api.php can be appended.
                    </p>
                </div>
                <div class="form-group">
                    <label>Category:</label>
                    <input type="text" name="category" value="Category:Blogs" size="80" class="form-control" />
                    <p class="help-block">
                        With <code>Category:</code> at the front (or whatever is appropriate for your language).
                    </p>
                </div>
                <div class="form-group">
                    <label>Number of items:</label>
                    <input type="text" name="num" value="10" size="10" class="form-control" />
                </div>
                <div class="form-group">
                    <input type="submit" value="Get feed" class="btn btn-info" />
                </div>
            </form>
            <p>
                To alter the date and time of a feed item, place a <code>&lt;time&gt;</code> element in the page.
                For example, see the {{blog post}} template on English Wikiversity.
            </p>
            <p>Please report issues on <a href="https://github.com/samwilson/mediawiki-feeds/issues">GitHub</a>.</p>
        </div>

        <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
        <!-- Include all compiled plugins (below), or include individual files as needed -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>  
    </body>
</html>
