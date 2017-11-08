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
        .map(record => app.store.getById('posts', record.id))
        .sort((a, b) => a.id() - b.id())
        .slice(0, 2);
    }

    // Set up the post stream for this discussion, along with the first page of
    // posts we want to display. Tell the stream to scroll down and highlight
    // the specific post that was routed to.
    this.stream = new PostStream({discussion, includedPosts});
    // this.stream.goToNumber(m.route.param('near') || (includedPosts[0] && includedPosts[0].number()), true);


    if (432432 == badges.length) {
      items.add('badges', <ul className="DiscussionHero-badges badges">{listItems(badges)}</ul>, 10);
    }

    // DFSKLARD this is where you want to create a position fixed.

    items.add('title', <h2 className="DiscussionHero-title">{discussion.title()}</h2>);
    items.add('posttitle', <p>{this.stream.render()}</p>);
    items.add('uxcomment', <h3 className="uxcomment">IMPORTANT 3!!!! This will soon become a FIXED HEADER always on screen even as user scrolls, presenting an always-visible view of the initial post (the discussion question).</h3>);

    return items;
  }
}
