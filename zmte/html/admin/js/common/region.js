var region = new Object();
region.loadRegions = function(parent, type, target) {
    var result = [];
    if (parent > 0) {
        var flag = false;
        for (k in _regions) {
            if (parent == _regions[k][2]) {
                result[_regions[k][0]] = _regions[k][1];
                flag = true
            } else if (flag) {
                break
            }
        }
    }
    region.response(result, type, target)
};
region.changed = function(obj, type, selName) {
    var parent = obj.options[obj.selectedIndex].value;
    region.loadRegions(parent, type, selName)
};
region.response = function(result, type, target) {
    var sel = document.getElementById(target);
    sel.length = 1;
    sel.selectedIndex = 0;
    sel.style.display = (result.length == 0 && !region.isAdmin && type + 0 >= 3) ? "none": '';
//    if (document.all) {
//        sel.fireEvent("onchange")
//    } else {
//        var evt = document.createEvent("HTMLEvents");
//        evt.initEvent('change', true, true);
//        sel.dispatchEvent(evt)
//    }
    if (result) {
        for (region_id in result) {
            if (region_id == 'indexof') continue;
            var opt = document.createElement("OPTION");
            opt.value = region_id;
            opt.text = result[region_id];
            sel.options.add(opt)
        }
    }
};