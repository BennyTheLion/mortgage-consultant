<?php
// The platform has no content of its own at the root — each business lives at
// /site/{slug}/. Send visitors who land on the bare app to the demo tenant so
// there's always something to see without knowing a real client's slug.
header('Location: site/demo/');
exit;
