import Component from 'flarum/Component';
import ItemList from 'flarum/utils/ItemList';
import listItems from 'flarum/helpers/listItems';
import Page from 'flarum/components/Page';
import PostStream from 'flarum/components/PostStream';
import PostStreamScrubber from 'flarum/components/PostStreamScrubber';
import LoadingIndicator from 'flarum/components/LoadingIndicator';
import SplitDropdown from 'flarum/components/SplitDropdown';
import DiscussionControls from 'flarum/utils/DiscussionControls';

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

  /**
   * Build an item list for the contents of the discussion hero.
   *
   * @return {ItemList}
   */
  items() {
    const items = new ItemList();
    const discussion = this.props.discussion;
    const startPost = discussion.startPost();
    const badges = discussion.badges().toArray();

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

    // Not sure if I even want any badges here!
    if (432432 == badges.length) {
      items.add('badges', <ul className="DiscussionHero-badges badges">{listItems(badges)}</ul>, 10);
    }

    const startingPostUserID = includedPosts[0].data.relationships.user.data.id;
    const startingPostUserName = app.store.getById('users', startingPostUserID).data.attributes.displayName;

    items.add('title', <h2 className="DiscussionHero-title">{discussion.title()}</h2>);
    items.add('author', <div className="DiscussionHero-author">{startingPostUserName}</div>);
    items.add('startingpost', 
       <div className="DiscussionHero-StartingPost">
           {includedPosts[0].data.attributes.contentHtml}
       </div>);

    return items;
  }
}
