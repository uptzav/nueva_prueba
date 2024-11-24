<x-admin::form.control-group.controls.inline.image
    ::name="'{{ $attribute->code }}'"
    ::value="'{{ route('admin.settings.attributes.download', ['path' => $value]) }}'"
    rules="required|mimes:jpeg,jpg,png,gif"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
/>
