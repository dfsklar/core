import Component from 'flarum/Component';
import LoadingIndicator from 'flarum/components/LoadingIndicator';
import TextEditor from 'flarum/components/TextEditor';
import avatar from 'flarum/helpers/avatar';
import listItems from 'flarum/helpers/listItems';
import ItemList from 'flarum/utils/ItemList';

/**
 * The `ComposerBody` component handles the body, or the content, of the
 * composer. Subclasses should implement the `onsubmit` method and override
 * `headerTimes`.
 *
 * ### Props
 *
 * - `originalContent`
 * - `submitLabel`
 * - `placeholder`
 * - `user`
 * - `confirmExit`
 * - `disabled`
 *
 * @abstract
 */
export default class ComposerBody extends Component {
  init() {
    /**
     * Whether or not the component is loading.
     *
     * @type {Boolean}
     */
    this.loading = false;


    // DFSKLARD here is where I plan to regexp the fact that this is an existing reply and hide the "coding".
    // This code is not really executed for a *NEW* reply -- for such an animal, the content here is still blank
    // because the insertMention has not yet been done yet (see the "mention" package extension).
    if (this.props.originalContent) {
      const regex_identify_reply = new RegExp(/^\@.*?\#(\d+) +/);
      if (this.props.originalContent.startsWith('@')) {
        const match = this.props.originalContent.match(regex_identify_reply);
        if (match) {
          this.props.mentionAnnotation = match[0];
          this.content = m.prop(this.props.originalContent.substr(match[0].length));
        }
      }
    }

    if (!(this.content)) {
      this.content = m.prop(this.props.originalContent);
    }


    /**
     * The text editor component instance.
     *
     * @type {TextEditor}
     */
    this.editor = new TextEditor({
      submitLabel: this.props.submitLabel,
      placeholder: this.props.placeholder,
      onchange: this.content,
      onsubmit: this.onsubmit.bind(this),
      value: this.content()
    });
  }

  view() {
    // If the component is loading, we should disable the text editor.
    this.editor.props.disabled = this.loading;

    return (
      <div className={'ComposerBody ' + (this.props.className || '')}>
        {avatar(this.props.user, {className: 'ComposerBody-avatar'})}
        <div className="ComposerBody-content">
          <ul className="ComposerBody-header">
             <li class="item-title"><i>Write your message below:</i></li></ul>
          <div className="ComposerBody-editor">{this.editor.render()}</div>
        </div>
        {LoadingIndicator.component({className: 'ComposerBody-loading' + (this.loading ? ' active' : '')})}
      </div>
    );
  }

  /**
   * Draw focus to the text editor.
   */
  focus() {
    this.$(':input:enabled:visible:first').focus();
  }

  /**
   * Check if there is any unsaved data – if there is, return a confirmation
   * message to prompt the user with.
   *
   * @return {String}
   */
  preventExit() {
    const content = this.content();

    return content && content !== this.props.originalContent && this.props.confirmExit;
  }

  /**
   * Build an item list for the composer's header.
   *
   * @return {ItemList}
   */
  headerItems() {
    return new ItemList();
  }

  /**
   * Handle the submit event of the text editor.
   *
   * @abstract
   */
  onsubmit() {
    var debug234552 = true;
    debugger;
    var jfieow = 3 / debug234552;
  }

  /**
   * Stop loading.
   */
  loaded() {
    this.loading = false;
    m.redraw();
  }
}
