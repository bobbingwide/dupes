<?php
/**
 * @copyright (C) Copyright Bobbing Wide 2022
 */

class Tests_issue_13459 extends BW_UnitTestCase {

    static private $saved_permalink_structure = null;

    private $duplicate_title;
	/**
	 * Permalink structure to test. Blank is for Plain permalinks ie https://example.com/?p=123
	 * @var string
	 */
    private $structure = '';
	/** 
	 * set up logic
	 * 
	 * - ensure any database updates are rolled back
	 */
	function setUp(): void {
		parent::setUp();
		$this->set_duplicate_title();
		$this->clear_all_duplicates();
	}

	function tearDown(): void {
	    $this->clear_all_duplicates();
    }

    static function setUpBeforeClass() : void {
        self::save_permalink_structure();
    }

    static function tearDownAfterClass() : void {
	    self::restore_permalink_structure();
	    self::flush_rules();
    }

    static function save_permalink_structure() {
        global $wp_rewrite;
        self::$saved_permalink_structure = $wp_rewrite->permalink_structure;
    }

    static function restore_permalink_structure() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( self::$saved_permalink_structure );
    }

    /**
     * Don't set a timestamp in a duplicate title.
     *
     * It makes it harder to remove duplicates when tests fail
     * and the tests are being run in situ.
     */
    function set_duplicate_title() {
	    $this->duplicate_title = 'duplicate title TRAC 13459';
	    //$this->duplicate_title .= date( '(Y-m-d H:i:s)');
    }

    /**
     * Since we're forcing commits in order to perform wp_remote_gets()
     * we need to ensure we've cleaned up any previous duplicates.
     *
     */
	function clear_all_duplicates() {
	    $posts = $this->fetch_all_duplicates();
	    $this->delete_all_duplicates( $posts );
        parent::commit_transaction();
        $refetched = $this->fetch_all_duplicates();
        $this->assertCount( 0, $refetched );
    }

    /**
     * Fetches all duplicates ( up to 10 ) with the duplicate title.
     *
     * @return int[]|WP_Post[]
     */
    function fetch_all_duplicates() {
	    $args = [ 'post_type' => 'any',
            'post_status' => 'any',
            'title' => $this->duplicate_title,
            'number_posts' => 10
            ];
	    $posts = get_posts( $args );
	    //print_r( $posts );
	    //gob();
        return $posts;
    }

    function delete_all_duplicates( $posts ) {
        foreach ($posts as $post) {
            //echo "deleting: " . $post->post_title . ' ' . $post->ID;
            if ( $this->duplicate_title === $post->post_title ) {
                $error = wp_delete_post($post->ID, true );
                //print_r( $error );
            } else {
                echo "Not deleting";
                print_r( $post );
            }

        }
    }

	/**
	 * This is supposed to set the permalink structure.
	 * How do we test that this has worked?
	 * What does the `set_permalink_structure()` method do? What about `flush_rules()`?
	 */
	function set_permalink_structure( $structure='/%postname%/') {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( $structure );
	}

	static function flush_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
		parent::commit_transaction();
	}

	/**
	 * Generates post content to uniquely identify the post.
	 * Includes the permalink structure.
	 * 
	 * @param $title
	 * @param $type
	 * @param $status
	 *
	 * @return string
	 */
	function generate_post_content( $title, $type, $status ) {
	    $content = $this->findable_paragraph();
        $content .= "$title:$type:$status:%ID%:";
        $content .= $this->structure;
        $content .= "</p>";
        return $content;
    }

    function findable_paragraph() {
	    return '<p class="duplicate-title-trac-13459">';
    }

	/**
	 * Helper method to create a post.
	 */
	function create_post( $type, $status ) {
	    $title = $this->duplicate_title;
		$content = $this->generate_post_content( $title, $type, $status);
		$args = array( 'post_content' => $content
			, 'post_type' => $type
			, 'post_title' => $title
			, 'post_status' => $status
			);
		$post = $this->factory->post->create( $args );
        $fetched = get_post( $post );
		$this->assertEquals( $content, $fetched->post_content );
		$this->update_post_content( $fetched );
		$fetched = get_post( $post );
		return $fetched;
	}

	/**
	 * Sets the post ID in the content and excerpt. We can't do it on the insert.
	 *
	 * @param $post
	 */
	function update_post_content( $post ) {
	    $postarr = [];
	    $postarr['ID'] = $post->ID;
	    $postarr['post_content'] = str_replace( '%ID%', $post->ID, $post->post_content );
	    $postarr['post_excerpt'] = $postarr['post_content'];
	    //print_r( $postarr );
	    wp_update_post( $postarr );
    }

	/**
	 * Attempts to fetch the post by its permalink.
	 *
	 * @param $post
	 */
	function fetch_post_by_permalink( $post ) {
		$permalink = get_permalink( $post );
		//echo "Fetching: " . $permalink;
		$args = [ 'sslverify' => false ];
		$result  = wp_remote_get( $permalink, $args);
		//echo "Result: ";
        $this->assertNotWPError( $result );
        if ( ! is_wp_error( $result ) ) {
            $response_code = wp_remote_retrieve_response_code( $result );
            //echo "Response code: " . $response_code;
            //print_r( $response_code );
            $this->assertEquals( 200, $response_code );
            if ( 200 === $response_code ) {
                $response = wp_remote_retrieve_body( $result );
                //print_r( $response );
                $result = $response;
            }
        }
		return $result;
	}

    /**
     * Checks that the post's content is in the fetched result.
     * @param $fetched
     * @param $post
     */
	function check_fetched( $fetched, $post )  {

	    $pos = strpos( $fetched, $this->findable_paragraph() );
	    $this->assertGreaterThan( 0, $pos );
        $fetched = substr( $fetched, $pos );
        //echo "Fetched:";
        //echo $fetched;
        $endpara = strpos( $fetched, "</p>");
        $fetched = substr( $fetched, 0, $endpara + 4 );
        // Checks for needle in haystack. Reverse of strpos().
	    $this->assertEquals( $post->post_content, $fetched );
    }

	/**
	 * These are sensible permalink structures.
	 *
	 * Non-sensible permalink structures are any structure which doesn't contain the %postname% or %post_id%
	 * eg /%monthnum%/
	 * @return string[]
	 */
    function permalink_structures() {
		$structures = [
			'',
			'/%year%/%monthnum%/%day%/%postname%/',
			'/%year%/%monthnum%/%postname%/',
			'/%post_id%/',
			'/%post_id%/%postname%/',
			'/%postname%/'
		];
		return $structures;
    }

	/**
     * Tests accessing posts with non-duplicated slugs.
     *
     * Two posts with the same title should have different slugs
     * and be individually accessible by their permalinks.
     */
	function test_duplicate_post_post() {
		//$structure = '/%postname%/';

		$this->set_permalink_structure( $this->structure );
		$this->clear_all_duplicates();

		$post1 = $this->create_post( 'post', 'publish');
		$post2 = $this->create_post( 'post', 'publish');

		$this->assertNotEquals( $post1->ID, $post2->ID );
		$this->assertNotEquals( $post1->post_name, $post2->post_name );
		//$this->assertFalse( true );
		//$this->flush_rules();
        parent::commit_transaction();
        $fetched1 = $this->fetch_post_by_permalink( $post1 );
        $this->check_fetched( $fetched1, $post1 );
        $fetched2 = $this->fetch_post_by_permalink( $post2 );
        $this->check_fetched( $fetched2, $post2 );
    }

    /**
     * Tests accessing a duplicate slug: page and post.
     *
     * When we request the post by its permalink then we should get the post.
     * When we request the page by its permalink then we should get the page.
     *
     * Obviously this can't happen when both posts have the same slug.
     * We need to indicate what we're looking for.
     * But WordPress doesn't yet realise that there's a problem.
     * And doesn't support using a `post_type=` attribute to enable the differentiation.
     */
    function test_duplicate_page_post() {
        $this->set_permalink_structure( $this->structure);
        $this->clear_all_duplicates();

        $page = $this->create_post( 'page', 'publish');
        $post = $this->create_post( 'post', 'publish');

        $this->assertNotEquals( $page->ID, $post->ID );
        // This will fail if and when the post's permalink is different from the page's
        $this->assertEquals( $page->post_name, $post->post_name );
        parent::commit_transaction();

        $fetched1 = $this->fetch_post_by_permalink( $page );
        $this->check_fetched( $fetched1, $page );
        $fetched2 = $this->fetch_post_by_permalink( $post );
        // This will fail while the page is being fetched instead of the post.
        // due to the post's permalink being the same as the page's.
        $this->check_fetched( $fetched2, $post );
    }

	/**
	 * Tests duplicate posts combinations for a range of permalink structures.
	 */
	function test_permalink_structures() {
		$structures = $this->permalink_structures();
		foreach ( $structures as $structure ) {
			$this->structure = $structure;
			$this->test_duplicate_post_post();
			//$this->test_duplicate_page_post();
		}
	}

	/**
	 * Tests duplicate page/post combinations for a range of permalink structures.
	 */
	function test_permalink_structures_page_post() {
		$structures = $this->permalink_structures();
		foreach ( $structures as $structure ) {
			$this->structure = $structure;
			//$this->test_duplicate_post_post();
			$this->test_duplicate_page_post();
		}
	}

}