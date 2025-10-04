<div class="report-widget widget-document-store">
    <h3><?= e(trans($this->property('title'))) ?></h3>

    <?php if (!isset($error)): ?>
        <div class="welcome-container">
            <div class="backend-content">
                <h2>Videos</h2>
                <ul class="videos">
                    <?php if (isset($videos)) {
                        foreach ($videos as $dirName => $dir) {
                            foreach ($dir as $name => $url) {
                                print("<li class='video-help'><i class='icon-video'></i>&nbsp;<span class='dir'>$dirName</span><a target='_blank' href='$url'>$name</a></li>");
                            }
                        }
                    } ?>
                </ul>

                <h2>Documents</h2>
                <ul class="documents">
                    <?php if (isset($documents)) {
                        foreach ($documents as $dirName => $dir) {
                            foreach ($dir as $name => $url) {
                                print("<li class='document'><i class='icon-file-text'></i>&nbsp;<span class='dir'>$dirName</span><a target='_blank' href='$url'>$name</a></li>");
                            }
                        }
                    } ?>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="callout callout-warning">
            <div class="content"><?= e($error) ?></div>
        </div>
    <?php endif ?>
</div>
