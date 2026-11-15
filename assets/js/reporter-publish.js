document.querySelectorAll('.news-publish-form').forEach(form => {
  const preview = form.querySelector('.news-publish-preview');
  form.querySelector('.news-preview-button').addEventListener('click', () => {
    const category = form.elements.category;
    const placements = Array.from(form.querySelectorAll('[name="placements[]"]:checked'))
      .map(input => input.parentElement.textContent.trim());
    preview.textContent = [form.elements.publish_title.value, form.elements.publish_lead.value,
      form.elements.publish_body.value, 'دسته: ' + category.options[category.selectedIndex].text,
      'جایگاه: ' + (placements.join('، ') || 'انتشار عادی'),
      'این پیش‌نمایش متن و انتخاب‌هاست؛ ظاهر نهایی را قالب سایت تعیین می‌کند.'].join('\n\n');
    preview.hidden = false;
  });
});
