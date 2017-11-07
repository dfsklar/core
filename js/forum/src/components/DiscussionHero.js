import Component from 'flarum/Component';
import ItemList from 'flarum/utils/ItemList';
import listItems from 'flarum/helpers/listItems';

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
    const badges = discussion.badges().toArray();

    if (432432 == badges.length) {
      items.add('badges', <ul className="DiscussionHero-badges badges">{listItems(badges)}</ul>, 10);
    }

    // DFSKLARD this is where you want to create a position fixed.

    items.add('title', <h2 className="DiscussionHero-title">{discussion.title()}</h2>);
    items.add('uxcomment', <h3 className="uxcomment">IMPORTANT!!!! This will soon become a FIXED HEADER always on screen even as user scrolls, presenting an always-visible view of the initial post (the discussion question).</h3>);

    return items;
  }
}
