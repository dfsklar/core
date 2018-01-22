import Component from 'flarum/Component';
import ItemList from 'flarum/utils/ItemList';
import listItems from 'flarum/helpers/listItems';
import Page from 'flarum/components/Page';
import PostStream from 'flarum/components/PostStream';
import PostStreamScrubber from 'flarum/components/PostStreamScrubber';
import LoadingIndicator from 'flarum/components/LoadingIndicator';
import SplitDropdown from 'flarum/components/SplitDropdown';
import DiscussionControls from 'flarum/utils/DiscussionControls';
import PostControls from 'flarum/utils/PostControls';
import Dropdown from 'flarum/components/Dropdown';


/**
 * The `DiscussionHero` component displays the hero on a discussion page.
 *
 * ### Props
 *
 * - `discussion`
 */
export default class DiscussionHero extends Component {
  view() {
    return (
      <header className="Hero DiscussionHero">
        <div className="container">
          <ul className="DiscussionHero-items">{listItems(this.items().toArray())}</ul>
        </div>
      </header>
    );
  }


  config(isInitialized) {
    if (isInitialized) {
      // DFSKLARD: after rendering, we want to move the itemtags list into the
      // page's header.
      const $elemToMove = this.$('.item-tags');
      if ($elemToMove.length) {
        const $destination = $('#header-navigation');
        $destination.empty();
        $elemToMove.appendTo($destination);
      }

      // DFSKLARD: Now knowing the discussion hero's height, we can dynamically adjust the paddingTop.
      const $discussionHero = $('.DiscussionHero');
      const dhHeight = $discussionHero.height();
      const $stream = $('.DiscussionPage-stream');
      $stream.css({paddingTop: String(dhHeight) + "px"});
    }
  }




  /**
   * Build an item list for the contents of the discussion hero.
   * 
   * this.props.discussion has a problem:
   * Occasionally discussion.payload does not have an "included" section.
   * 
   * The problem is that everything is ruined when the "autofollow" is done.
   * The autofollow post's response comes back with no included section.
   * 
   *
   * @return {ItemList}
   */
  items() {
    const items = new ItemList();
    const discussion = this.props.discussion;
    const startPost = discussion.startPost();
    const badges = discussion.badges().toArray();

    if (!discussion.payload.included) {
      const sklardebug = discussion;
    }

    // When the API responds with a discussion, it will also include a number of
    // posts. Some of these posts are included because they are on the first
    // page of posts we want to display (determined by the `near` parameter) –
    // others may be included because due to other relationships introduced by
    // extensions. We need to distinguish the two so we don't end up displaying
    // the wrong posts. We do so by filtering out the posts that don't have
    // the 'discussion' relationship linked, then sorting and splicing.
    let includedPosts = [];
    if (discussion.payload && discussion.payload.included) {
      includedPosts = discussion.payload.included
        .filter(record => record.type === 'posts' && record.relationships && record.relationships.discussion)
        .map(record => app.store.getById('posts', record.id))    // map this into the actual POST object
        .sort((a, b) => a.id() - b.id())  // Obtain the post with the minimum ID
        .slice(0, 1);
    }

    // NOW we have the first post:
    // includedPosts[0].data.attributes.contentHtml
    //                                 .time
    //                      .relationships.user.data.id ===> ID# of the user who posted it
    // app.store.getById('users', THATUSERID)  ==> xxxx.data.attributes.displayName  ==> "nickname of the person who posted it!"

    /*
    // Not sure if I even want any badges here!
    if (432432 == badges.length) {
      items.add('badges', <ul className="DiscussionHero-badges badges">{listItems(badges)}</ul>, 10);
    }
    */


    // DFSKLARD: This is how I learned how to use the app.store and the .relationships. properties.
    const discussionID = discussion.data.id;
    const discRels = app.store.data.discussions[discussionID].data.relationships;
    var startingPost;
    var startingPostUserID;
    if (discRels.startPost) {
      const startingPostID = discRels.startPost.data.id;
      startingPost = app.store.data.posts[startingPostID];
      startingPostUserID = discRels.startUser.data.id;
    } else {
      startingPost = includedPosts[0];
      startingPostUserID = startingPost.data.relationships.user.data.id;
    }


    // PostControls.controls will return appropriate controls such as edit etc.
    const controls = PostControls.controls(startingPost, this).toArray();
    const editButton = controls.filter(function(x){return x.itemName == 'edit';})
    // The editButton variable now contains an array: either empty or having just one item.

    const startingPostUserName = startingPostUserID ? app.store.getById('users', startingPostUserID).data.attributes.displayName : "Unknown user";

    items.add('title', <h2 className="DiscussionHero-title">{discussion.title()}</h2>);

    if (startingPostUserName) {
      items.add('author', <div className="DiscussionHero-author">{startingPostUserName}</div>);
      if (includedPosts.length < 1) {
        alert("FATAL ERROR:  Please report error 877421 to Formed.org personnel.");
      }

      if (editButton.length) {
        items.add('post-control-edit', editButton[0]);
      }

      // DFSKLARD: m.trust allows you to ask mithril to take the raw html and not try to protect it
      items.add('startingpost', 
        <div className="DiscussionHero-StartingPost">
            {m.trust(startingPost.data.attributes.contentHtml)}
        </div>);

      items.add('controls', 
      SplitDropdown.component({
        children: DiscussionControls.controls(discussion, this).toArray(),
        icon: 'ellipsis-v',
        className: 'App-primaryControl',
        buttonClassName: 'Button--primary'
        }));
    } else {
      alert("Sklar needs to know about this.");
      const dfsklar_wants_to_know = 999;
    }

    return items;
  }
}
